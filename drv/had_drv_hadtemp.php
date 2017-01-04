<?php
/***************************************************************************
 * File: had_drv_hadtemp.php                             Part of homeautod *
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

class had_drv_hadtemp extends had_drv_class {

	private $check_last = 0;
	private $check_interval = 10; // Check every 10 seconds

	protected $ep = array(
		0 => array('type' => 'AI', 'io_type' => 'input'),
		1 => array('type' => 'AI', 'io_type' => 'input')
	);

	public function init() {
	}

	public function get_data() {

		if((time() - $this->check_last) >= $this->check_interval) {

			$url = "http://{$this->path}/";

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			if(($ret = curl_exec($ch)) === FALSE)
				throw new Exception("Unable to get {$url}: " . curl_error($ch));

			curl_close($ch);
			
			if(($jdata = json_decode($ret, 1)) === NULL)
				throw new Exception('Invalid json-data från hadtemp device');

			if(array_key_exists('temperature', $jdata) && array_key_exists('humidity', $jdata)) {

				$epdata = array(
					array(
						'ep' => 0,
						'type' => HAD_EP_DT_VALUE,
						'data' => $jdata['temperature'],
						'unit' => 'C'
					),
					array(
						'ep' => 1,
						'type' => HAD_EP_DT_VALUE,
						'data' => $jdata['humidity'],
						'unit' => '%'
					)
				);

				echo "EPDATA: " . create_had_packet($epdata) . "\n";
			}

			$this->check_last = time();
		}

	}
}
