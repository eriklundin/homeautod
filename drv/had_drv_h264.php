<?php
/***************************************************************************
 * File: had_drv_h264.php                                Part of homeautod *
 *                                                                         *
 * Copyright (C) 2015 Erik Lundin. All Rights Reserved.                    *
 *                                                                         *
 * This program is free software; you can redistribute it and/or modify    *
 * it under the terms of the GNU General Public License as published by    *
 * the Free Software Foundation; either version 3 of the License, or	   *
 * (at your option) any later version.                                     *
 *                                                                         *
 * This program is distributed in the hope that it will be useful,         *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of          *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           *
 * GNU General Public License for more details.                            *
 *                                                                         *
 * You should have received a copy of the GNU General Public License	   *
 * along with homeautod.  If not, see <http://www.gnu.org/licenses/>.	   *
 *                                                                         *
 ***************************************************************************/

require_once('lib/lib.rtsp.php');
require_once('lib/lib.rtp.php');

class had_drv_h264 extends had_drv_class {

	private $sock = NULL;
	private $port = 554;

	private $rtsp = NULL;
	private $rtp = NULL;

	private $outbuf = '';
	private $inbuf = '';
	private $dumping = false;

	private $select_timeout = 20000;

	private $udpsock = NULL;
	private $udp_lastdata = 0; // Last time we received udp data
	private $udp_timeout = 6; // 3 second timeout

	private $url = '/axis-media/media.amp';

	protected $ep = array(
		0 => array('type' => 'Video stream', 'index' => 0, 'io_type' => 'output')
	);

	public function init() {

		// Check that we have all the settings we need
		if(!array_key_exists('username', $this->settings))
			throw new Exception("username is not defined in settings for device");
		if(!array_key_exists('password', $this->settings))
			throw new Exception("password is not defined in settings for device");

		$this->logger->log(LOG_DEBUG, "Connected to {$this->path}:{$this->port}");

		// Create the socket.
		if(($this->sock = stream_socket_client("tcp://{$this->path}:{$this->port}", $errno, $errstr, 30)) === FALSE)
			throw new Exception("Unable to connect to {$this->path}:{$this->port}: {$errstr}");

		if(stream_set_blocking($this->sock, false) === false)
			throw new Exception("Unable to set socket to non-blocking");

		// Open the UDP client socket
		$this->logger->log(LOG_INFO, "Listening on UDP port {$this->settings['udpport']}");
		if(($this->udpsock = stream_socket_server("udp://0.0.0.0:{$this->settings['udpport']}", $errno, $errstr, STREAM_SERVER_BIND)) === FALSE)
			throw new Exception("Unable to create client UDP socket: $errstr");

		if(stream_set_blocking($this->udpsock, false) === false)
			throw new Exception("Unable to set UDP socket to non-blocking");

		$this->rtsp = new rtsp(
			$this->logger,
			$this->url,
			$this->settings['udpport'],
			$this->settings['username'],
			$this->settings['password']
		);

		$this->rtsp->describe();
		$this->rtp = new rtp($this->logger);

		echo "EPDATA: " . create_had_packet(array(array('ep' => 0, 'type' => HAD_EP_DT_STATUS, 'status' => HAD_EP_STATUS_LOW))) . "\n";
	}

	public function get_data() {

		// Check if the rtsp-stream needs to send keepalive
		$this->rtsp->check_keepalive();

		// Start counting from the first the the mode it set to play
		if($this->udp_lastdata == 0 && $this->rtsp->getmode() == RTSP_MODE_PLAY)
			$this->udp_lastdata = time();

		// Check if the udp stream has timed out
		if((time() - $this->udp_lastdata) >= $this->udp_timeout && $this->rtsp->getmode() == RTSP_MODE_PLAY)
			throw new Exception("No data received in UDP stream after {$this->udp_timeout} seconds");

		if(in_array($this->sock, $this->a_write)) {

			if(($b = fwrite($this->sock, $this->outbuf, 1024)) === FALSE)
				throw new Exception("Unable to write to socket: " . socket_strerror(socket_last_error()));

			//echo "DEBUG: Wrote $b bytes [" . substr($this->outbuf, 0, $b) . "]\n";
			$this->outbuf = substr($this->outbuf, $b);
		}

		if(in_array($this->udpsock, $this->a_read)) {

			while(true) {
		               	$sr = fread($this->udpsock, 4096);
				if(empty($sr))
					break; // No more data to read
				if(strlen($sr) > 0) {

					if($this->rtsp->getmode() != RTSP_MODE_PLAY && $this->rtsp->getmode() != RTSP_MODE_TEARDOWN) {
						// We received udp data though the stream isn't started yet.
						throw new Exception("Received UDP-data though stream isn't started yet. Old stream still sending data?");
					}

					$this->udp_lastdata = time();
					$this->rtp->decode_frame($sr);
				}
			}

		}

		if(in_array($this->sock, $this->a_read)) {
			$sr = fread($this->sock, 200);
			if($sr === false)
				throw new Exception("Unable to read from socket: ". socket_strerror(socket_last_error()));
			if(strlen($sr) > 0) {
				$this->inbuf .= $sr;
				while($this->rtsp->process_data($this->inbuf) === true) {}
			}
		}

	}

	public function get_select() {

		$ret = array(
			'a_read' => array(),
			'a_write' => array(),
			'a_except' => array(),
		);

		// Append any data that needs to be sent
		if(($d = $this->rtsp->get_queue_send_data()) !== FALSE)
			$this->outbuf .= $d;

		if($this->sock != NULL) {
			$ret['a_read'][] = $this->sock;
			if(!empty($this->outbuf))
				$ret['a_write'][] = $this->sock;
			$ret['a_except'][] = $this->sock;
		}

		if($this->udpsock != NULL) {
			$ret['a_read'][] = $this->udpsock;
			$ret['a_except'][] = $this->udpsock;
		}

		return $ret;
	}

	public function set_data($cmd, $data) {

		$action = NULL;
		switch($cmd) {
			case 'SETEPDATA':
				foreach($data as $d) {
					if($d['epnumber'] == 0) {
						$action = $d['status'];
					}
				}
			break;
		}

		if($action == HAD_EP_STATUS_LOW && $this->dumping === true) {

			$this->rtp->stop_dump();
			$this->dumping = false;
			echo "EPDATA: " . create_had_packet(array(array('ep' => 0, 'type' => HAD_EP_DT_STATUS, 'status' => HAD_EP_STATUS_LOW))) . "\n";

		} else if($action == HAD_EP_STATUS_HIGH && $this->dumping === false) {

			$date = date('Ymd_His');
			$dirname = HAD_DEV_DATA_PATH . "/{$this->devid}/videos";
			if(!file_exists($dirname)) {
				if(mkdir($dirname, 0755, TRUE) === FALSE)
					throw new Exception("Unable to create directory '{$dirname}'");
			}

			$fname = "{$dirname}/{$date}.h264";
			$this->rtp->start_dump($fname, $this->rtsp->get_sprop());
			$this->dumping = true;

			echo "EPDATA: " . create_had_packet(array(array('ep' => 0, 'type' => HAD_EP_DT_STATUS, 'status' => HAD_EP_STATUS_HIGH))) . "\n";
		}

	}

	public function deinit() {

		if(isset($this->rtsp) && $this->rtsp->getmode() == RTSP_MODE_PLAY) {
			$this->logger->log(LOG_INFO, "Tearing down UDP-stream");
			$this->rtsp->teardown();
			return false;
		}

		if(isset($this->rtsp) && $this->rtsp->getmode() == RTSP_MODE_TEARDOWN) {
			// Wait for the teardown
			return false;
		}

		if($this->sock != NULL)
			fclose($this->sock);
		if($this->udpsock != NULL)
			fclose($this->udpsock);

		$this->rtp->stop_dump();
	}
}
