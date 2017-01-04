<?php
/***************************************************************************
 * File: had_drv_zwave.php                               Part of homeautod *
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

require_once('lib/lib.zwave.php');

class had_drv_zwave extends had_drv_class {

	private $fh = NULL;
	private $inbuf = '';
	private $outbuf = '';
	private $baudrate = '115200';
	private $checkinterval = 10;

	protected $ep = array();

	private function process_value_update($epdata) {

		$epret = array();
		foreach($epdata as $e) {

			if(isset($e['data'])) {
				$epret[] = array(
					'ep' => $e['epnum'],
					'type' => HAD_EP_DT_VALUE,
					'data' => $e['data'],
					'unit' => $e['unit']
				);
			} else if(isset($e['status'])) {

				if(isset($this->ep[$e['epnum']['status']])) {
					if($this->ep[$e['epnum']['status']] == $e['status']) {
						// Nothing was changed
						continue;
					} else {
						$epret[] = array(
							'ep' => $e['epnum'],
							'type' => HAD_EP_DT_EVENT,
							'status' => HAD_EP_STATUS_CHANGED
						);
					}
				}

				$epret[] = array(
					'ep' => $e['epnum'],
					'type' => HAD_EP_DT_STATUS,
					'status' => $e['status']
				);
				$this->ep[$e['epnum']['status']] = $e['status'];

			}
		}

		if(!empty($epret))
			echo "EPDATA: " . create_had_packet($epret) . "\n";

	}

	private function process_ep_update($epdata) {
		foreach($epdata as $e) {
			if(!array_key_exists($e['epnum'], $this->ep)) {
				$this->ep[$e['epnum']] = array(
					'type' => $e['type'],
					'io_type' => $e['io_type'],
					'checktime' => 0
				);
			}
		}
		if(!empty($this->ep))
		echo "EPUPDATE: " . create_had_packet($this->ep) . "\n";
	}


	public function set_data($cmd, $data) {
		switch($cmd) {
			case 'SETEPDATA':
				foreach($data as $d) {
					if(!array_key_exists($d['epnumber'], $this->ep)) {
						$this->logger->log(LOG_WARNING, "Invalid endpoint number: {$d['epnumber']}");
						continue;
					}

					if(!empty($d['status']))
						$this->zwave->set_switch_on($d['epnumber']);
					else
						$this->zwave->set_switch_off($d['epnumber']);
				}
			break;
			default:
				$this->logger->log(LOG_WARNING, "Unknown command: $cmd");
		}
        }


	public function get_data() {

		if(in_array($this->fh, $this->a_write)) {

			if(($b = fwrite($this->fh, $this->outbuf, 1024)) === false)
				throw new Exception("Unable to write to file handle {$this->path}");

			if($b > 0) {
				$this->logger->log(LOG_DEBUG, "Wrote $b bytes [" . bin2hexstring(substr($this->outbuf, 0, $b)) . "]");
				$this->outbuf = substr($this->outbuf, $b);
			}
		}

		if(in_array($this->fh, $this->a_read)) {
			$sr = fread($this->fh, 1024);
			if($sr === false)
				throw new Exception("Unable to read from file handle");
			if(strlen($sr) > 0) {

				$this->logger->log(LOG_DEBUG, "Read: [" . bin2hexstring($sr) . "]");

				$this->inbuf .= $sr;
				$ret = $this->zwave->process_data($this->inbuf);
				if(!empty($ret['type'])) {
					switch($ret['type']) {
						case 'epupdatevalue':
							$this->process_value_update($ret['endpoints']);
						break;
						case 'epupdate':
							$this->process_ep_update($ret['endpoints']);
						break;
						default:
							$this->logger->log(LOG_WARNING, "Unknown returntype from zwave object: {$ret['type']}");
					}
				}
			}
		}

		return true;
	}


	public function get_select() {

		// Check if we need to poll any devices
		$mtime = microtime(true);
		foreach($this->ep as $n => $v) {
			if($v['type'] == 'DO' && $v['io_type'] == 'output' && (time() - $v['checktime']) >= $this->checkinterval) {
				$this->zwave->get_binary_switch_value($n);
				$this->ep[$n]['checktime'] = $mtime;
			}

			if($v['type'] == 'AI' && $v['io_type'] == 'input' && (time() - $v['checktime']) >= $this->checkinterval) {
			//	$this->zwave->get_meter_value($n);
				$this->ep[$n]['checktime'] = $mtime;
			}
		}

		// Check if theres any packages waiting to send.
		$d = $this->zwave->get_queue_send_data();
		if($d !== false)
			$this->outbuf .= $d;

		$ret = array();
		$ret['a_read'] = array($this->fh);
		$ret['a_write'] = (!empty($this->outbuf)?array($this->fh):array());
		$ret['a_except'] = array($this->fh);

		return $ret;
	}

	public function init() {

		exec("/bin/stty -F {$this->path} {$this->baudrate} -cstopb -parenb raw", $output, $retval);

		if(($this->fh = fopen($this->path, 'c+')) === false)
			throw new Exception("Unable to open {$this->path}");

		if(stream_set_blocking($this->fh, false) === false)
			throw new Exception("Unable to set file handle to non-blocking");

		$this->zwave = new zwave($this->logger);
		$this->zwave->get_nodes();
	}

	public function deinit() {
		if($this->fh != NULL)
			fclose($this->fh);
	}
}
