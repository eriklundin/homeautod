<?php
/***************************************************************************
 * File: had_drv_tinysine_tosr02.php                     Part of homeautod *
 *                                                                         *
 * Copyright (C) 2016 Erik Lundin. All Rights Reserved.                    *
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

require_once('lib/lib.tinysine_tosr.php');

class had_drv_tinysine_tosr02 extends had_drv_class {

	private $sock = NULL;
	private $tosr = NULL;
	private $port = 2000;
	private $outbuf = '';
	private $inbuf = '';
	private $lastcheck = 0;
	private $checkinterval = 2;

	// Tinysine TOSR02 has 2x DO
	protected $ep = array(
                0 => array('type' => 'DO', 'index' => 1, 'io_type' => 'output'),
                1 => array('type' => 'DO', 'index' => 2, 'io_type' => 'output')
	);


	public function set_data($cmd, $data) {

		if(!isset($this->tosr))
			return;

		switch($cmd) {
			case 'SETEPDATA':
				foreach($data as $d) {
					if(!array_key_exists($d['epnumber'], $this->ep)) {
						$this->logger->log(LOG_WARNING, "Invalid endpoint number: {$d['epnumber']}");
						continue;
					}
					$this->tosr->set_output($this->ep[$d['epnumber']]['index'], $d['status']);
				}
			break;
			default:
				$this->logger->log(LOG_WARNING, "Unknown command: $cmd");
			}
        }


	private function process_data() {

		if(($ret = $this->tosr->process_data($this->inbuf)) !== false) {

			if($ret['cmd'] == TOSR_CMD_READ_STATUS) {
				$epdata = array();
				foreach($ret['data'] as $n => $v) {
					foreach($this->ep as $en => $ev) {

						if($n != $ev['index'])
							continue;

						if(!array_key_exists('state', $ev) || $this->ep[$en]['state'] != $v) {
							$epdata[] = array(
								'ep' => $en,
								'type' => 'HAD_EP_DT_STATUS',
								'status' => $v
							);
							$this->ep[$en]['state'] = $v;
						}
					}
				}

				if(!empty($epdata))
					echo "EPDATA: " . create_had_packet($epdata) . "\n";
			}
		}
	}

	public function init() {

		// Create the socket.
		if(($this->sock = stream_socket_client("tcp://{$this->path}:{$this->port}", $errno, $errstr, 30)) === FALSE)
			throw new Exception("Unable to connect to {$this->path}:{$this->port}: {$errstr}");

		$this->logger->log(LOG_DEBUG, "Connected to {$this->path}:{$this->port}");

		if(stream_set_blocking($this->sock, false) === false)
			throw new Exception("Unable to set socket to non-blocking");

		$this->tosr = new tinysine_tosr();
                $this->tosr->read_states();
	}

	public function get_data() {

		if(in_array($this->sock, $this->a_write)) {
			if(($b = fwrite($this->sock, $this->outbuf, 1024)) === FALSE)
				throw new Exception("Unable to write to socket: " . socket_strerror(socket_last_error()));
			if($b == 0)
				throw new Exception("Unable to write to socket");

			echo "WROTE: {$this->outbuf}\n";
			$this->outbuf = substr($this->outbuf, $b);
		}

		if(in_array($this->sock, $this->a_read)) {
			$sr = fread($this->sock, 1024);
			if($sr === false)
				throw new Exception("Unable to read from socket: ". socket_strerror(socket_last_error()));
			if(strlen($sr) > 0) {
			$this->inbuf .= $sr;
				while($this->process_data() === true) {}
			}
		}

		if(in_array($this->sock, $this->a_except)) {
			throw new Exception("Got a socket exception");
		}

		return true;
	}

	public function get_select() {

		$mtime = microtime(true);
		if($this->tosr->is_queue_empty() === true && ($mtime - $this->lastcheck) > $this->checkinterval) {
			$this->tosr->read_states();
			$this->lastcheck = $mtime;
		}

		// Check if theres any packages waiting to send.
		$d = $this->tosr->get_queue_send_data();
		if($d !== false)
			$this->outbuf .= $d;

		$ret = array();

		$ret['a_read'] = array($this->sock);
		$ret['a_write'] = (!empty($this->outbuf) ? array($this->sock) : array());
		$ret['a_except'] = array($this->sock);

		return $ret;
	}

	public function deinit() {

		if($this->sock != NULL)
			fclose($this->sock);
	}
}
