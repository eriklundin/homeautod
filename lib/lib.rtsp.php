<?php
/***************************************************************************
 * File: lib.rtsp.php                                    Part of homeautod *
 *                                                                         *
 * Copyright (C) 2015 Erik Lundin. All Rights Reserved.                    *
 *                                                                         *
 * This program is free software; you can redistribute it and/or modify    *
 * it under the terms of the GNU General Public License as published by    *
 * the Free Software Foundation; either version 3 of the License, or       *
 * (at your option) any later version.                                     *
 *                                                                         *
 * This program is distributed in the hope that it will be useful,         *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of          *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           *
 * GNU General Public License for more details.                            *
 *                                                                         *
 * You should have received a copy of the GNU General Public License       *
 * along with homeautod.  If not, see <http://www.gnu.org/licenses/>.      *
 *                                                                         *
 ***************************************************************************/

require_once('lib.h264.php');

define('RTSP_MODE_START',	0);
define('RTSP_MODE_DESCRIBE',	1);
define('RTSP_MODE_SETUP',	2);
define('RTSP_MODE_PLAY',	3);
define('RTSP_MODE_TEARDOWN',	4);
define('RTSP_MODE_QUIT',	5);

define('RTSP_PACKET_STATUS_NEW',  0);
define('RTSP_PACKET_STATUS_SENT', 1);

define('RTSP_PACKET_MODE_HEADER',	0);
define('RTSP_PACKET_MODE_DATA',		1);

class rtsp {

	private $logger = NULL;
	private $queue = array();
	private $time_lastcmd = 0;
	private $mode = RTSP_MODE_START;
	private $cseq = 0;
	private $sprop1_corrected = false;

	private $username = NULL;
	private $password = NULL;
	private $settings = array();

	public function check_keepalive() {
		if($this->mode == RTSP_MODE_PLAY) {
			if((time() - $this->time_lastcmd) >= ($this->settings['session_timeout'] / 2)) {
				$this->logger->log(LOG_DEBUG, "Sending keepalive (OPTIONS)");
				$this->options();
			}
		}
	}

	private function process_headers(&$data) {

		if(($pos = strpos($data, "\r\n\r\n")) === FALSE)
			return false;

		$this->logger->log(LOG_DEBUG, "Processing headers for {$this->queue[0]['cmd']}");

		$hdata = substr($data, 0, $pos);
		$hr = explode("\r\n", $hdata);

		// Cut out the processed data
		if(strlen($data) == $pos + 4) {
			$data = '';
		} else {
			$data = substr($data, $pos + 4);
		}

		if(empty($hr))
			throw new Exception('Received invalid data from rtsp-stream');

		// Validate the first line
		if(!preg_match("/^RTSP\/[0-9\.]+ (\d+) (.+)$/", array_shift($hr), $frow))
			throw new Exception("First line in rtsp response header was invalid: {$hr[0]}");

		$cseq = 0;
		foreach($hr as $h) {
			if(preg_match("/^CSeq:\s*(\d+)$/i", $h, $m)) {
				$cseq = $m[1];
			} else if(preg_match("/^WWW-Authenticate: Digest (.+)$/i", $h, $m)) {
				$x = explode(", ", $m[1]);
				$val = array();
				foreach($x as $y) {
					if(preg_match("/^([^=]+)=\"*([^\"]+)\"*$/", $y, $m2)) {
						if($m2[1] == 'realm')
							$this->settings['realm'] = $m2[2];
						else if($m2[1] == 'nonce')
							$this->settings['nonce'] = $m2[2];
					}
				}
			} else if(preg_match("/^Content-Length:\s*(\d+)$/i", $h, $m)) {
				$this->queue[0]['contentlength'] = $m[1];
			} else if(preg_match("/^Session: ([^;]+); timeout=(\d+)/i", $h, $m)) {
				$this->settings['session'] = $m[1];
				$this->settings['session_timeout'] = $m[2];
			} else if(preg_match("/^RTP-Info: .*;seq=(\d+);/", $h, $m)) {
				$this->settings['seqnum'] = $m[1];
			}

		}

		// Sanity checking
		if($this->queue[0]['cseq'] != $cseq)
			throw new Exception("Expected cseq {$this->queue[0]} but received {$cseq}");

		if($frow[1] == '200') {
			// Normal operations
		} else if($frow[1] == '401') {

			if(!empty($this->queue[0]['auth']))
				throw new Exception("Received {$frow[2]} ({$frow[1]}) in response to {$this->queue[0]['cmd']}. Invalid username or password?");

			$this->logger->log(LOG_INFO, "Logging in...");
			$this->describe();
			return;
		} else {
			// Woops
			throw new Exception("Received invalid response: {$frow[2]} ({$frow[1]}) to command {$this->queue[0]['cmd']}");
		}

	}

	public function process_data(&$data) {

		if(empty($this->queue))
			throw new Exception('Received data to process when queue was empty' . $data);

		if($this->queue[0]['mode'] == RTSP_PACKET_MODE_HEADER) {

			if($this->process_headers($data) === FALSE)
				return;

			if(!empty($this->queue[0]['contentlength'])) {
				$this->queue[0]['mode'] = RTSP_PACKET_MODE_DATA;
				if(empty($data))
					return;

			} else {

				switch($this->queue[0]['cmd']) {
					case 'SETUP':
						$this->play();
					break;
					case 'TEARDOWN':
						$this->mode = RTSP_MODE_QUIT;
					break;
				}

				array_shift($this->queue);
				$this->queue = array_values($this->queue);
				return;
			}
		}

		// Have we received all data?
		if(strlen($data) < $this->queue[0]['contentlength'])
			return;

		$drows = explode("\r\n", $data);
		foreach($drows as $d) {
			if(preg_match("/^a=control:(.+)$/", $d, $m)) {
				$this->settings['control'] = $m[1];
			} else if(preg_match("/a=framerate:(\d+\.\d+)/", $d, $m)) {
				$this->settings['framerate'] = floatval($m[1]);
			} else if(preg_match("/a=fmtp.+sprop-parameter-sets=([A-Za-z0-9=]+),([a-zA-Z0-9=]+)$/", $d, $m)) {
				$this->settings['sprop1'] = base64_decode($m[1]);
				$this->settings['sprop2'] = base64_decode($m[2]);
			}
		}

		switch($this->queue[0]['cmd']) {
			case 'DESCRIBE':
				$this->setup();
			break;

		}

		$data = '';
		array_shift($this->queue);
		$this->queue = array_values($this->queue);

	}

	private function add_to_queue($cmd, $data = array()) {

		$n = array();
		$this->cseq++;

		if($this->mode == RTSP_MODE_SETUP) {
			$n['raw'] = "{$cmd} {$this->settings['url']}/{$this->settings['control']} RTSP/1.0\r\n";
		} else {
			$n['raw'] = "{$cmd} {$this->settings['url']} RTSP/1.0\r\n";
		}

		foreach($data as $d)
			$n['raw'] .= "$d\r\n";

		// Login if needed
	        if(!empty($this->settings['realm']) && !empty($this->settings['nonce'])) {
			$ha1 = md5("{$this->username}:{$this->settings['realm']}:{$this->password}");
			$ha2 = md5("{$cmd}:{$this->settings['url']}");
			$response = md5("{$ha1}:{$this->settings['nonce']}:{$ha2}");
			$n['auth'] = true;
			$n['raw'] .= "Authorization: Digest username=\"{$this->username}\", realm=\"{$this->settings['realm']}\", nonce=\"{$this->settings['nonce']}\", uri=\"{$this->settings['url']}\", response=\"{$response}\"\r\n";
		}

		if(!empty($this->settings['session'])) {
			$n['raw'] .= "Session: {$this->settings['session']}\r\n";
		}

		$n['raw'] .= "CSeq: {$this->cseq}\r\n";
		$n['raw'] .= "User-Agent: homeautod\r\n";
		$n['raw'] .= "\r\n";

		$n['cmd'] = $cmd;
		$n['cseq'] = $this->cseq;
		$n['status'] = RTSP_PACKET_STATUS_NEW;
		$n['mode'] = RTSP_PACKET_MODE_HEADER;
		$this->queue[] = $n;
	}	

	public function get_queue_send_data() {

		if(empty($this->queue))
			return false;

		if($this->queue[0]['status'] == RTSP_PACKET_STATUS_NEW) {
			$this->queue[0]['status'] = RTSP_PACKET_STATUS_SENT;
			$this->time_lastcmd = time();
			return $this->queue[0]['raw'];
		} else {
			return false;
		}

	}

	public function get_sprop() {

		if(empty($this->settings['sprop1']))
			throw new Exception("sprop1 is not set");
		if(empty($this->settings['sprop2']))
			throw new Exception("sprop2 is not set");

		if($this->sprop1_corrected === false) {

			$sdata = h264::decode_sps($this->settings['sprop1']);
			if(empty($sdata['vui_timing_info_present_flag'])) {

				if(!isset($this->settings['framerate']))
					throw new Exception("SPS had no timing information and no framerate was provided by RTSP");

				$sdata['vui_timing_info_present_flag'] = 1;
				$sdata['vui_num_units_in_tick'] = 1000;
				$sdata['vui_time_scale'] = intval($this->settings['framerate'] * $sdata['vui_num_units_in_tick'] * 2);
				$sdata['vui_fixed_frame_rate_flag'] = 1;
				$this->settings['sprop1'] = h264::encode_sps($sdata);
			}

			$this->sprop_corrected = true;
		}

		return array(
			'sprop1' => $this->settings['sprop1'],
			'sprop2' => $this->settings['sprop2']
		);
	}

	public function getmode() {
		return $this->mode;
	}

	public function describe() {
		$this->mode = RTSP_MODE_DESCRIBE;
		$this->add_to_queue('DESCRIBE', array('Accept: application/sdp'));
	}

	public function setup() {

		if(($pos = strpos($this->settings['control'], $this->settings['url'])) !== FALSE) {
			$this->settings['control'] = substr($this->settings['control'], $pos + strlen($this->settings['url']));
		}

		$this->mode = RTSP_MODE_SETUP;
		$this->add_to_queue('SETUP', array(
			"Transport: RTP/AVP;unicast;client_port={$this->settings['udpport']}-{$this->settings['udpport']}"
		));
	}

	public function play() {
		$this->mode = RTSP_MODE_PLAY;
		$this->add_to_queue('PLAY', array('Range: npt=0.000-'));
	}

	public function teardown() {
		$this->mode = RTSP_MODE_TEARDOWN;
		$this->add_to_queue('TEARDOWN');
	}

	public function options() {
		$this->add_to_queue('OPTIONS');
	}

	function __construct($logger, $url, $udpport, $username, $password) {
		$this->logger = $logger;
		$this->settings['url'] = $url;
		$this->settings['udpport'] = $udpport;
		$this->username = $username;
		$this->password = $password;
	}

}
