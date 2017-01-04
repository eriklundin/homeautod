<?php
/***************************************************************************
 * File: lib.xbee.php                                    Part of homeautod *
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

class zwave {

	private $logger = NULL;
	private $nodes = array();
	private $queue = array();
	private $send_ack = false;
	private $callbackid = 0x01;
	private $time_check_state = 10; // Check state every 10 seconds
	private $timeout = 3; // Seconds before a command times out

	private $functxt = array(
		0x02 => 'DiscoveryNodes',
		0x04 => 'ApplicationCommandHandler',
		0x13 => 'SendData',
		0x15 => 'GetVersion',
		0x49 => 'ApplicationUpdate',
		0x60 => 'RequestNodeInfo'
	);

	private $func = array(
		'DiscoveryNodes'		=> 0x02,
		'ApplicationCommandHandler'	=> 0x04,
		'SendData'			=> 0x13,
		'GetVersion'			=> 0x15,
		'ApplicationUpdate'		=> 0x49,
		'RequestNodeInfo'		=> 0x60
	);

	private $cmdclass = array(
		'SWITCH_BINARY' => 0x25,
		'METER'		=> 0x32
	);

	private $cmdclasstxt = array(
		0x25 => 'SWITCH_BINARY',
		0x32 => 'METER'
	);

	private function calc_epnum($node, $cmdclass, $i) {
		$ret = $node * 1000000;
		$ret += $cmdclass * 1000;
		$ret += $i;
		return $ret;
	}

	private function epnum_to_array($epnum) {
		$ret = str_pad($epnum, 9, '0', STR_PAD_LEFT);
		return array(
			'node' => intval(substr($ret, 0, 3)),
			'cmdclass' => intval(substr($ret, 3, 3)),
			'i' => intval(substr($ret, 6, 3))
		);
	}

	private function print_frame($data) {
		$len = strlen($data);
		echo "  Type: " . bin2hex($data[2]) . " (" . (ord($data[2]) == 1 ? 'RESPONSE' : 'REQUEST'). ")\n";
		echo "  Function: " . bin2hex($data[3]) . " ({$this->functxt[ord($data[3])]})\n";
		$fdata = substr($data, 4);
		echo "  Data: " . bin2hexstring($fdata) . "\n";
	}


	private function process_app_cmd_handler($data) {

		// ApplicationCommandHandler
		$ret = array(
			'type' => 'epupdatevalue',
			'endpoints' => array()
		);

		$node = ord($data[5]);
		$cmdclass = ord($data[7]);

//		echo "ApplicationCommandHandler: " . bin2hexstring($data) . "\n";
//		echo "  Node: $node\n";
//		echo "  CmdClass: " . $this->cmdclasstxt[$cmdclass] . "\n";

		$status = NULL;
		$value = NULL;
		switch($cmdclass) {
			case $this->cmdclass['SWITCH_BINARY']:
				if(ord($data[9]) == 0xFF) {
					$status = 1;
				} else {
					$status = 0;
				}
			break;
			case $this->cmdclass['METER']:
				$meterdata = substr($data, 13, 2);
				$metervalue = unpack('n', $meterdata);
				$metervalue = array_shift($metervalue);
				$value = $metervalue/10;
				echo "METER DATA: $metervalue\n";
				echo "GOT DATA: " . bin2hexstring($data) . "\n";
			break;
			default:
				echo "Invalid cmdclass: 0x" . dechex($cmdclass) . "\n";
		}

		if(isset($status)) {
			$ret['endpoints'][] = array(
				'epnum' => $this->calc_epnum($node, $cmdclass, 1),
				'status' => $status
			);
		}

		if(isset($status)) {
			$ret['endpoints'][] = array(
				'epnum' => $this->calc_epnum($node, $cmdclass, 1),
				'data' => $value,
				'unit' => 'XXX'
			);
		}


		if(!empty($ret['endpoints']))
			return $ret;
	}

	private function find_in_queue($expected_cmd) {
		foreach($this->queue as $n => $v) {
			if($v['expected_reply'] == $expected_cmd)
				return $n;
		}
		return FALSE;
	}

	public function process_data(&$data) {

		$len = strlen($data);
		$ret = NULL;

		if($len == 0)
			return;

		// Ack
		if($data[0] == chr(0x06)) {
			$data = substr($data, 1);
			if(!empty($this->queue)) {
				$this->queue[0]['ack'] = true;
			} else {
				$this->logger->log(LOG_WARNING, "Received ack when queue was empty: " . bin2hexstring($data));
			}
			return;
		}

		if($data[0] == chr(0x01)) {

			if($len < 2)
				return;

			$flen = ord($data[1]);
			if($len < $flen + 2)
				return;

			$checksum = $data[$flen + 1];
			$fdata = substr($data, 0, $flen + 1);

			// Cut out the frame
			if(($data = substr($data, $flen + 2)) === FALSE)
				$data = '';

			$cchecksum = $this->calc_checksum($fdata);
			if($checksum != $cchecksum)
				throw new Exception("Got invalid checksum: " . bin2hex($checksum) . ". Expected " . bin2hex($cchecksum));

			$cmd = ord($fdata[3]);

			// Check if it's a delivery frame
			if(ord($fdata[2]) == 0x01 && !empty($this->queue) && $cmd == $this->queue[0]['cmd'] && ord($fdata[4]) == 0x01) {
				echo "Got a delivery frame\n";
				$this->send_ack = true;
				return;
			}

			if(ord($fdata[2]) == 0x00 && !empty($this->queue) && $cmd == $this->queue[0]['cmd'] && $cmd != $this->queue[0]['expected_reply']) {
				$callbackid = ord($fdata[$flen]);
				echo "Got SendData Request with callback id: $callbackid\n";
				$this->send_ack = true;
				return;
			}
	

			switch($cmd) {

				case $this->func['ApplicationCommandHandler']:
					$ret = $this->process_app_cmd_handler($fdata);
				break;

				case $this->func['ApplicationUpdate']:

					$ret = array(
						'type' => 'epupdate',
						'endpoints' => array()
					);

					$node = ord($fdata[5]);
					$this->nodes[$node] = array(
						'cmdclasses' => array(),
						'time_statecheck' => 0
					);

					for($i = 10; $i < $len - 1; $i++) {
						//echo "Command class: " . bin2hex($fdata[$i]) . "\n";
						$this->nodes[$node]['cmdclasses'][] = ord($fdata[$i]);
						$cmdclass = ord($fdata[$i]);

						$type = NULL;
						switch($cmdclass) {
							case $this->cmdclass['SWITCH_BINARY']:
								$type = 'DO';
								$io_type = 'output';
							break;
							case $this->cmdclass['METER']:
								$type = 'AI';
								$io_type = 'input';
							break;
						}

						if(!is_null($type)) {
							$ret['endpoints'][] = array(
								'epnum' =>  $this->calc_epnum($node, $cmdclass, 1),
								'type' => $type,
								'io_type' => $io_type
							);
						}

					}

//					if(in_array($this->cmdclass['SWITCH_BINARY'], $this->nodes[$node]['cmdclasses']))
//						$this->get_binary_switch_value($node);
//					if(in_array($this->cmdclass['METER'], $this->nodes[$node]['cmdclasses']))
//						$this->get_meter_value($node);

				break;

				case $this->func['DiscoveryNodes']:
					$len = ord($fdata[6]);
					$index = 0;
					for($i = 7; $i < $len + 7; $i++) {
						$num = 1;
						for($c = 0; $c <= 7; $c++) {
							$index++;
							if((ord($fdata[$i]) & $num) > 0) {
								$this->logger->log(LOG_INFO, "Found node: $index");
								$this->ep[$index] = array();
								if($index != 1)
									$this->RequestNodeInfo($index);
							}
							$num = $num * 2;
						}
					}
				break;
			}

			$this->send_ack = true;
			if(($qi = $this->find_in_queue($cmd)) !== FALSE) {
				unset($this->queue[$qi]);
				$this->queue = array_values($this->queue);
			} else {
				echo "Unexpected data: " . bin2hexstring($fdata) . "\n";
			}
		}


		if(!is_null($ret))
			return $ret;
		
	}

	public function get_queue_send_data() {

		if($this->send_ack === true) {
			$this->send_ack = false;
			return chr(0x06);
		}

		if(empty($this->queue))
			return false;

		if($this->queue[0]['status'] == HAD_DRV_QUE_PKT_STAT_SENT) {
			if(($diff = time() - $this->queue[0]['time_sent']) > $this->timeout) {
				echo "Timeout after $diff seconds\n";
				$this->queue[0]['status'] = HAD_DRV_QUE_PKT_STAT_NEW;
				$this->queue[0]['retries']++;
			}
		}

		if($this->queue[0]['status'] == HAD_DRV_QUE_PKT_STAT_NEW) {
			$this->queue[0]['time_sent'] = time();
			$this->queue[0]['status'] = HAD_DRV_QUE_PKT_STAT_SENT;
			return $this->queue[0]['raw'];
		} else {
			return false;
		}
	}

	private function calc_checksum($data) {
		$l = 0xFF;
		$len = strlen($data);
		for($i = 1; $i < $len; $i++)
			$l ^= ord($data[$i]);
		return chr($l);
	}

	private function create_zwave_frame($data) {
		$len = strlen($data) + 2;
		$f = chr(0x01) . chr($len) . chr(0x00) . $data;
		$checksum = $this->calc_checksum($f);
		$f .= $checksum . chr(0x0d);
		return $f;
	}

	private function add_to_queue($cmd, $expected_reply, $data = NULL) {

		$n = array();
		$n['cmd'] = $cmd;
		$n['status'] = HAD_DRV_QUE_PKT_STAT_NEW;
		$n['ack'] = false;
		$n['time_sent'] = false;
		$n['retries'] = 0;
		$n['expected_reply'] = $expected_reply;

		$sdata = chr($cmd);
		if(!is_null($data))
			$sdata .= $data;

		$n['callbackid'] = $this->callbackid;
		$sdata .= chr($this->callbackid);

		if($this->callbackid == 0xFF)
			$this->callbackid = 0X01;
		else
			$this->callbackid++;

		$n['raw'] = $this->create_zwave_frame($sdata);
		$this->queue[] = $n;
	}

	public function get_version() {
		$this->add_to_queue(
			$this->func['GetVersion'],
			$this->func['GetVersion']
		);
	}

	public function get_nodes() {
		$this->add_to_queue(
			$this->func['DiscoveryNodes'],
			$this->func['DiscoveryNodes']
		);
	}

	public function RequestNodeInfo($id) {
		$this->add_to_queue(
			$this->func['RequestNodeInfo'],
			$this->func['ApplicationUpdate'],
			chr($id)
		);
	}

	public function get_meter_value($node) {
		$this->add_to_queue(
			$this->func['SendData'],
			$this->func['ApplicationCommandHandler'],
			chr($node) . chr(0x03) . chr($this->cmdclass['METER']) . chr(0x01) . chr(0x00) . chr(0x25)
		);
	}

	public function get_binary_switch_value($node) {
		$e = $this->epnum_to_array($node);
		$this->add_to_queue(
			$this->func['SendData'],
			$this->func['ApplicationCommandHandler'],
			chr($e['node']) . chr(0x02) . chr($this->cmdclass['SWITCH_BINARY']) . chr(0x02) . chr(0x25)
		);
	}

	public function set_switch_off($node) {
		$e = $this->epnum_to_array($node);
		$this->add_to_queue(
			$this->func['SendData'],
			$this->func['SendData'],
			chr($e['node']) . chr(0x03) . chr(0x25) . chr(0x01) . chr(0x00) . chr(0x25) . chr(0x25)
		);
	}

	public function set_switch_on($node) {
		$e = $this->epnum_to_array($node);
		$this->add_to_queue(
			$this->func['SendData'],
			$this->func['SendData'],
			chr($e['node']) . chr(0x03) . chr(0x25) . chr(0x01) . chr(0xFF) . chr(0x25) . chr(0x25)
		);
	}

	public function __construct($logger) {
		$this->logger = $logger;
	}
}
