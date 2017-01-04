<?php
/***************************************************************************
 * File: lib.brainboxes.php                              Part of homeautod *
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

define('BB_CMD_READ_FIRMWARE',	0);
define('BB_CMD_READ_MODEL',		1);
define('BB_CMD_READ_COUNTER',	2);
define('BB_CMD_RESET_COUNTER',	3);
define('BB_CMD_READ_IOSTATUS',	4);
define('BB_CMD_SET_DO',			5);

define('BB_PACKET_STATUS_NEW',	0);
define('BB_PACKET_STATUS_SENT',	1);

class brainboxes {

	private $uid = '01';
	private $queue = array();

	public function process_data(&$data) {

		if(empty($this->queue))
			throw new Exception("Got data to process while the queue was empty");

		if(($pos = strpos($data, "\x0d")) === false)
			return false;

		$bdata = substr($data, 0, $pos);
		$data = substr($data, $pos + 1);

		$ret = array();
		$q = $this->queue[0];
		switch($q['cmd']) {
			case BB_CMD_READ_MODEL:
				if(!preg_match("/^!\d{2}(.+)$/", $bdata, $m))
					throw new Exception("Invalid model data: $bdata");
				$ret['data'] = $m[1];
			break;
			case BB_CMD_READ_FIRMWARE:
				if(!preg_match("/^!\d{2}(.+)$/", $bdata, $m))
					throw new Exception("Invalid firmware data: $bdata");
				$ret['data'] = $m[1];
			break;
			case BB_CMD_READ_COUNTER:
				if(!preg_match("/^!\d{2}(\d+)$/", $bdata, $m))
					throw new Exception("Invalid read counter data: $bdata");
				$ret['data'] = $m[1];
			break;
			case BB_CMD_RESET_COUNTER:
				if(!preg_match("/^!\d{2}$/", $bdata, $m))
					throw new Exception("Invalid reset counter data: $bdata");
			break;
			case BB_CMD_READ_IOSTATUS:
				if(!preg_match("/^!([0-9A-F]{2})([0-9A-F]{2})00$/", $bdata, $m))
					throw new Exception("Invalid iostatus status: $bdata");

				// Decode each IO status
				$t1 = strrev(str_pad(sprintf("%b", hexdec($m[1])), 8, '0', STR_PAD_LEFT));
				$t2 = strrev(str_pad(sprintf("%b", hexdec($m[2])), 8, '0', STR_PAD_LEFT));

				$ret['data']['output'] = array();
				$ret['data']['input'] = array();

				for($i = 0; $i < 8; $i++) {
					$ret['data']['output'][$i] = intval($t1[$i]);
					$ret['data']['input'][$i] = intval($t2[$i]);
				}

			break;
			case BB_CMD_SET_DO:
				if(!preg_match("/^>$/", $bdata))
					throw new Exception("Invalid response to BB_CMD_SET_DO: $bdata");
			break;
			default:
				throw new Exception("Unknown cmd in queue: {$q['cmd']}");
		}

		$ret['cmd'] = $q['cmd'];
		$ret['id'] = $q['id'];

		// Shift out the queue item
		array_shift($this->queue);
		$this->queue = array_values($this->queue);

		return $ret;
	}

	public function get_queue_send_data() {
		if(empty($this->queue))
			return false;

		if($this->queue[0]['status'] == BB_PACKET_STATUS_NEW) {
			$this->queue[0]['status'] = BB_PACKET_STATUS_SENT;
			return $this->queue[0]['raw'];
		} else {
			return false;
		}
	}

	private function add_to_queue($cmd, $arg = '') {

		switch($cmd) {
			case BB_CMD_READ_FIRMWARE:
				$raw = "\${$this->uid}F";
			break;
			case BB_CMD_READ_MODEL:
				$raw = "\${$this->uid}M";
			break;
			case BB_CMD_READ_COUNTER:
				$raw = "#{$this->uid}{$arg}";
			break;
			case BB_CMD_RESET_COUNTER:
				$raw = "\${$this->uid}C{$arg}";
			break;
			case BB_CMD_READ_IOSTATUS:
				$raw = "\${$this->uid}6";
			break;
			case BB_CMD_SET_DO:
				$raw = "#{$this->uid}1{$arg}";
			break;
			default:
				throw new Exception("Unknown cmd: $cmd");
		}

		$n = array();
		$n['cmd'] = $cmd;
		$n['raw'] = "{$raw}\r";
		$n['id'] = $arg;
		$n['status'] = BB_PACKET_STATUS_NEW;
		$this->queue[] = $n;

	}

	public function read_firmware() {
		$this->add_to_queue(BB_CMD_READ_FIRMWARE);
	}

	public function read_model() {
		$this->add_to_queue(BB_CMD_READ_MODEL);
	}

	public function read_counter($id) {
		$this->add_to_queue(BB_CMD_READ_COUNTER, $id);
	}

	public function reset_counter($id) {
		$this->add_to_queue(BB_CMD_RESET_COUNTER, $id);
	}

	public function read_io_status() {
		$this->add_to_queue(BB_CMD_READ_IOSTATUS);
	}

	public function set_do($id, $status) {
		$this->add_to_queue(BB_CMD_SET_DO, dechex($id) . str_pad($status, 2, '0', STR_PAD_LEFT));
	}

	public function is_queue_empty() {
		if(empty($this->queue))
			return true;
		else
			return false;
	}

}
