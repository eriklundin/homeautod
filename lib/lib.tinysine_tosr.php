<?php
/***************************************************************************
 * File: lib.tinysine_tosr.php                           Part of homeautod *
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

require_once('lib.utils.php');

define('TOSR_CMD_READ_STATUS',		1);
define('TOSR_CMD_READ_TEMPERATURE',	2);
define('TOSR_CMD_SET_DO',		3);

define('TOSR_STATUS_INIT',		1);
define('TOSR_STATUS_READY',		2);

class tinysine_tosr {

	private $hellostring = '*HELLO*';
	private $status = TOSR_STATUS_INIT;
	private $queue = array();
	private $do_index = array(
		'on' => array(
			1 => 'e',
			2 => 'f',
			3 => 'g',
			4 => 'h',
			5 => 'i',
			6 => 'j',
			7 => 'k',
			8 => 'l'
		),
		'off' => array(
			1 => 'o',
			2 => 'p',
			3 => 'q',
			4 => 'r',
			5 => 's',
			6 => 't',
			7 => 'u',
			8 => 'v'
		)
	);

	public function process_data(&$data) {

		if(($pos = strpos($data, $this->hellostring)) !== FALSE) {
			$data = substr($data, $pos + strlen($this->hellostring));
			$this->status = TOSR_STATUS_READY;
			return true;
		}

		$ret = array();

		if($this->queue[0]['status'] == TOSR_CMD_READ_STATUS) {
			$status = str_pad(decbin(ord($data[0])), 8, '0', STR_PAD_LEFT);
			for($i = 7; $i > -1; $i--) {
				$ret['data'][8-$i] = $status[$i];
			}
			$data = substr($data, 1);
		}

		if(!empty($data))
			throw new Exception("UNKNOWN DATA: " . bin2hexstring($data));

		$ret['cmd'] = $this->queue[0]['status'];

		// Shift out the queue item
		array_shift($this->queue);
		$this->queue = array_values($this->queue);

		return $ret;
	}

	private function add_to_queue($cmd, $raw) {
		$n = array();
		$n['cmd'] = $cmd;
		$n['raw'] = "$raw\r";
		$n['status'] = PACKET_STATUS_NEW;
		$this->queue[] = $n;
	}

	public function read_states() {
		$this->add_to_queue(TOSR_CMD_READ_STATUS, '[');
	}

	public function get_temperature() {
		$this->add_to_queue(TOSR_CMD_READ_TEMPERATURE, 'b');
	}

	public function set_output($index, $status) {

		if(empty($status))
			$s = 'off';
		else
			$s = 'on';

		if(!array_key_exists($index, $this->do_index[$s]))
			throw new Exception("Index {$index} does not exist on this device");

		$this->add_to_queue(TOSR_CMD_SET_DO, $this->do_index[$s][$index]);
	}

	public function get_queue_send_data() {

		if(empty($this->queue))
			return false;

		// Don't send any data until we got the hello-string
		if($this->status != TOSR_STATUS_READY)
			return false;

		if($this->queue[0]['status'] == PACKET_STATUS_NEW) {

			$this->queue[0]['status'] = PACKET_STATUS_SENT;
			$ret = $this->queue[0]['raw'];
			// Updating IO's doesn't get a response
			if($this->queue[0]['cmd'] == TOSR_CMD_SET_DO) {
				array_shift($this->queue);
				$this->queue = array_values($this->queue);	
			}

			return $ret;
		} else {
			return false;
		}
	}

	public function is_queue_empty() {
		if(empty($this->queue))
			return true;
		else
			return false;
        }
}

