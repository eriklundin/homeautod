<?php
/***************************************************************************
 * File: lib.config.php                                  Part of homeautod *
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

class config {

	private $data = array();

	public function __construct($cfgfile) {

		if(!file_exists($cfgfile))
			throw new Exception("Configuration file '$cfgfile' does not exist");

		if(($data = file_get_contents($cfgfile)) === false)
			throw new Exception("Unable to read contents of configuration file '$cfgfile'");

		$rows = explode("\n", $data);
		foreach($rows as $r) {
			if(preg_match("/^\s*([A-Za-z0-9\[\]_]+)\s*=(.*)$/", $r, $m)) {
				if(preg_match('/\s*"(.+)"\s*/', $m[2], $m2)) {
					$value = $m2[1];
				} else {
					$value = trim($m[2]);
				}
				$this->data[$m[1]] = $value;
			}

		}
	}

	/** Reads a configuration value */
	public function read($name) {
		if(isset($this->data[$name]))
			return $this->data[$name];
		else
			return NULL;
	}

	/** Returns the pool of udp-ports */
	public function get_udppool() {

		if(($str = $this->read('udprange')) === NULL)
			throw new Exception('udprange is not defined in the config-file');

		if(!preg_match("/^(\d+)\-(\d+)$/", $str, $match))
			throw new Exception("udprange is invalid: {$str} (Should be: <num1>-<num2>)");

		$ret = array();

		$ret['low_port'] = $match[1];
		$ret['high_port'] = $match[2];
		$ret['ports'] = array();

		if($ret['low_port'] > $ret['high_port'])
			throw new Exception("The first port in udprange can not be smaller then the second port: {$str}");

		return $ret;
	}

	public function get_udpport(&$pool, $drvid) {

		if($pool['high_port'] - $pool['low_port'] == count($pool['ports']))
				throw new Exception('There are no free udp-ports in the pool');

		while(true) {
			$port = rand($pool['low_port'], $pool['high_port']);
			if(!array_key_exists($port, $pool['ports'])) {
				$pool['ports'][$port] = $drvid;
				break;
			}
		}
		return $port;
	}


	public function free_udpports(&$pool, $drvid) {
		foreach($pool['ports'] as $n => $v){
			if($v == $drvid)
				unset($pool['ports'][$n]);
		}
	}
}
