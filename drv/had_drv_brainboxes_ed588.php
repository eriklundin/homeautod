<?php
/***************************************************************************
 * File: had_drv_brainboxes_ed588.php                    Part of homeautod *
 *                                                                         *
 * Copyright (C) 2015 Erik Lundin. All Rights Reserved.                    *
 *     	       	       	       	       	       	       	       	       	   *
 * This program is free software; you can redistribute it and/or modify	   *
 * it under the terms of the GNU General Public License as published by	   *
 * the Free Software Foundation; either version 3 of the License, or   	   *
 * (at your option) any later version. 	       	       	       	       	   *
 *     	       	       	       	       	       	       	       	       	   *
 * This program is distributed in the hope that it will be useful,     	   *
 * but WITHOUT ANY WARRANTY; without even the implied warranty of      	   *
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the       	   *
 * GNU General Public License for more details.	       	       	       	   *
 *     	       	       	       	       	       	       	       	       	   *
 * You should have received a copy of the GNU General Public License   	   *
 * along with homeautod.  If not, see <http://www.gnu.org/licenses/>.  	   *
 *     	       	       	       	       	       	       	       	       	   *
 ***************************************************************************/

require_once('lib/lib.brainboxes.php');
require_once('lib/lib.utils.php');

class had_drv_brainboxes_ed588 extends had_drv_class {

	private $sock = NULL;
	private $port = 9500;
	private $inbuf = '';
	private $outbuf = '';
	private $queue = array();
	private $lastcheck = 0;
	private $checkinterval = 0.2;

	// Brainboxes ED-588 has 8x DI and 8x DO
	protected $ep = array(
		0 => array('type' => 'DI', 'index' => 0, 'io_type' => 'input'),
		1 => array('type' => 'DI', 'index' => 1, 'io_type' => 'input'),
		2 => array('type' => 'DI', 'index' => 2, 'io_type' => 'input'),
		3 => array('type' => 'DI', 'index' => 3, 'io_type' => 'input'),
		4 => array('type' => 'DI', 'index' => 4, 'io_type' => 'input'),
		5 => array('type' => 'DI', 'index' => 5, 'io_type' => 'input'),
		6 => array('type' => 'DI', 'index' => 6, 'io_type' => 'input'),
		7 => array('type' => 'DI', 'index' => 7, 'io_type' => 'input'),
		8 => array('type' => 'DO', 'index' => 0, 'io_type' => 'output'),
		9 => array('type' => 'DO', 'index' => 1, 'io_type' => 'output'),
		10 => array('type' => 'DO', 'index' => 2, 'io_type' => 'output'),
		11 => array('type' => 'DO', 'index' => 3, 'io_type' => 'output'),
		12 => array('type' => 'DO', 'index' => 4, 'io_type' => 'output'),
		13 => array('type' => 'DO', 'index' => 5, 'io_type' => 'output'),
		14 => array('type' => 'DO', 'index' => 6, 'io_type' => 'output'),
		15 => array('type' => 'DO', 'index' => 7, 'io_type' => 'output'),
	);

	public function set_data($cmd, $data) {

		if(!isset($this->bb))
			return;

		switch($cmd) {
			case 'SETEPDATA':
				foreach($data as $d) {
					if(!array_key_exists($d['epnumber'], $this->ep)) {
						$this->logger->log(LOG_WARNING, "Invalid endpoint number: {$d['epnumber']}");
						continue;
					}
					$this->bb->set_do($this->ep[$d['epnumber']]['index'], $d['status']);
				}
			break;
			default:
				$this->logger->log(LOG_WARNING, "Unknown command: $cmd");
		}
	}

	private function process_data() {
		if(($ret = $this->bb->process_data($this->inbuf)) !== false) {
			switch($ret['cmd']) {
				case BB_CMD_READ_MODEL:
					$this->model = $ret['data'];
				break;
				case BB_CMD_READ_FIRMWARE:
					$this->firmware = $ret['data'];
				break;
				case BB_CMD_READ_COUNTER:
					$c = intval($ret['data']);
					if($c > 0) {

						$found = 0;
						foreach($this->ep as $n => $v) {
							if($v['type'] == 'DI' && $v['index'] == $ret['id']) {
								$eid = $n;
								$found = 1;
							}
						}

						if($found == 0)
							throw new Exception("Unable to find DI with index {$ret['id']}");

						$epdata = array(
							array(
								'ep' => $eid,
								'type' => HAD_EP_DT_EVENT,
								'status' => HAD_EP_STATUS_CHANGED,
								'data' => $c
							)
						);

						echo "EPDATA: " . create_had_packet($epdata) . "\n";
					}
				break;
				case BB_CMD_SET_DO:
				case BB_CMD_RESET_COUNTER:
					// Ignore.
				break;
				case BB_CMD_READ_IOSTATUS:

					$epdata = array();

					// Set the status of the IO
					foreach($this->ep as $n => $v) {

						switch($v['type']) {
							case 'DI':
								$t = 'input';
							break;
							case 'DO':
								$t = 'output';
							break;
							default:
								throw new Exception("Unknown type: {$v['type']}");
						}

						if(empty($ret['data'][$t][$v['index']]))
							$s = HAD_EP_STATUS_LOW;
						else
							$s = HAD_EP_STATUS_HIGH;

						if(!array_key_exists('state', $this->ep[$n]) || $this->ep[$n]['state'] != $ret['data'][$t][$v['index']])
							$epdata[] = array('ep' => $n, 'type' => HAD_EP_DT_STATUS, 'status' => $s);
						$this->ep[$n]['state'] = $ret['data'][$t][$v['index']];
					}

					if(!empty($epdata))				
						echo "EPDATA: " . create_had_packet($epdata) . "\n";

				break;
				default:
					throw new Exception("Unknown cmd from process_data: {$ret['cmd']}");
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

		$this->bb = new brainboxes();
		$this->bb->read_model();
		$this->bb->read_firmware();
	}


	public function get_select() {

		$mtime = microtime(true);

		if($this->bb->is_queue_empty() === true && ($mtime - $this->lastcheck) > $this->checkinterval) {

			$this->bb->read_io_status();

			foreach($this->ep as $e) {
				if($e['type'] == 'DI') {
					$this->bb->read_counter($e['index']);
					$this->bb->reset_counter($e['index']);
				}
			}
			$this->lastcheck = $mtime;
		}


		// Check if theres any packages waiting to send.
		$d = $this->bb->get_queue_send_data();
		if($d !== false)
			$this->outbuf .= $d;

		$ret['a_read'] = array($this->sock);
		$ret['a_write'] = (!empty($this->outbuf)?array($this->sock):array());
		$ret['a_except'] = array($this->sock);
		return $ret;
	}

	public function get_data() {

		if(in_array($this->sock, $this->a_write)) {

			if(($b = fwrite($this->sock, $this->outbuf, 1024)) === FALSE)
				throw new Exception("Unable to write to socket: " . socket_strerror(socket_last_error()));

			if($b == 0)
				throw new Exception("Unable to write to socket");

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

		return true;
	}


	public function deinit() {

		if($this->sock != NULL)
			fclose($this->sock);

		$this->logger->log(LOG_DEBUG, "DEINIT");
	}
}
