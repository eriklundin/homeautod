<?php
/***************************************************************************
 * File: lib.devices.php                                 Part of homeautod *
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

require_once('lib.constants.php');
require_once('lib.drivers.php');

function load_devices() {
	global $database, $logger;

	$dev = $database->getdata("SELECT * FROM devices WHERE enabled = '1'");

	foreach($dev as $n => $v) {
		if(init_device($dev[$n]) === FALSE)
			unset($dev[$n]);
	}

	return $dev;
}

function init_device(&$d) {
	global $logger, $database, $g_drvlist;

	if(!array_key_exists($d['driver'], $g_drvlist)) {
		$logger->log(LOG_WARNING, "Ignoring device {$d['id']} with unknown driver: {$d['driver']}");
		return FALSE;
	}

	// Convert the settings to an array
	if(!is_null($d['settings']))
		$d['settings'] = json_decode($d['settings'], 1);

	$d['exit'] = false;
	$d['started_time'] = 0;

	$eps = $database->getdata("SELECT * FROM endpoints WHERE device_id = '{$d['id']}'");
	foreach($g_drvlist[$d['driver']]['ep'] as $en => $ev) {

		$found = 0;
		foreach($eps as $e) {
			if($e['epnumber'] == $en) {
				$found = 1;
				if($e['io_type'] != $ev['io_type'] || $e['type'] != $ev['type']) {
					$logger->log(LOG_WARNING, "Updating invalid endpoint {$en} on device {$d['id']}");
					$database->setdata(
						sprintf("UPDATE endpoints SET io_type = '%s', type = '%s' WHERE id = '%d'",
							$ev['io_type'], $ev['type'], $e['id'])
					);
				}
			}
		}

		if($found == 0) {
			$logger->log(LOG_WARNING, "Unable to find endpoint {$en} for device {$d['id']}. Adding...");
			$database->setdata(
				sprintf("INSERT INTO endpoints(epnumber,device_id,io_type,type) VALUES('%d','%d','%s','%s')",
					$en, $d['id'], $ev['io_type'], $ev['type'])
			);
		}
	}

	// Load all corrected endpoints
	$d['endpoints'] = $database->getdata("SELECT * FROM endpoints WHERE device_id = '{$d['id']}'");

	$d['pipes'] = array();
	$d['inbuf'] = '';
	$d['outbuf'] = '';
	$d['status'] = HAD_DEV_STATUS_LOADED;

}

function start_device(&$d, $logger, $config) {
	global $g_udppool;

	$cmd = "/usr/lib/homeautod/had_drv -i {$d['id']} -p {$d['path']} -d {$d['driver']}";
	$logger->log(LOG_DEBUG, "Starting driver with: $cmd");

	$d['starttime'] = time();

	$desc = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w')
	);

	if(($d['proc'] = proc_open($cmd, $desc, $d['pipes'])) === false) {
		echo "ERROR\n";
		$logger->log(LOG_WARNING, "Unable to start device {$d['name']}");
		$d['status'] = HAD_DEV_STATUS_ERROR;
		return;
	}

	if(!is_resource($d['proc'])) {
		echo "ERROR\n";
		$logger->log(LOG_WARNING, "Unable to start device {$d['name']}");
		$d['status'] = HAD_DEV_STATUS_ERROR;
		return;		
	}

	stream_set_blocking($d['pipes'][1], 0);
	stream_set_blocking($d['pipes'][2], 0);

	$d['status'] = HAD_DEV_STATUS_STARTED;
	$d['started_time'] = time();
	$settings = array(
		'timezone' => $config->read('timezone'),
		'udpport' => $config->get_udpport($g_udppool, $d['id']),
		'endpoints' => array()
	);

	// Add the defined endpoints if the device uses the list from the database
	foreach($d['endpoints'] as $n => $v) {
		$settings['endpoints'][] = array(
			'name' => $v['name'],
			'epnumber' => $v['epnumber'],
			'type' => $v['type'],
			'io_type' => $v['io_type'],
			'parameters' => json_decode($v['parameters'])
		);
	}

	// Add the settings from the device overwriting any previously
	// declared default values from homeautod.
	if(is_array($d['settings']))
		$settings = array_merge($settings, $d['settings']);

	$d['outbuf'] .= "SETTINGS:" . create_had_packet($settings) . "\n";

}

function stop_device($id) {
	global $logger, $g_devices;

	$id = intval($id);

	$found = false;
	foreach($g_devices as $n => $v) {
		if($v['id'] == $id) {
			$found = true;
			break;
		}
	}

	if(!$found)
		throw new Exception("Unable to stop device with id {$id} (Not found)");

	// Check if the process has been started
	if($v['status'] != HAD_DEV_STATUS_STARTED || empty($g_devices[$n]['proc']))
		return;

	$logger->log(LOG_INFO, "Sending kill signal to device '{$v['name']}'");
	$g_devices[$n]['status'] = HAD_DEV_STATUS_STOP;
	proc_terminate($g_devices[$n]['proc']);
}

function ep_status_txt($ep_status) {
	switch($ep_status) {
		case HAD_EP_STATUS_LOW:
			return 'LOW';
		case HAD_EP_STATUS_HIGH:
			return 'HIGH';
		default:
			return 'UNKNOWN';
	}
}

function find_endpoint_by_epnum($devid, $epnumber) {
	global $g_devices;
	foreach($g_devices as $d) {

		if($d['id'] != $devid)
			continue;

		foreach($d['endpoints'] as $e) {
			if($e['epnumber'] == $epnumber)
				return $e;
		}
	}
	return FALSE;
}

function find_endpoint_by_id($id, $returndevindex = FALSE) {
	global $g_devices;
	foreach($g_devices as $n => $d) {
		foreach($d['endpoints'] as $e) {
			if($e['id'] == $id) {
				if($returndevindex)
					return $n;
				else
					return $e;
			}
		}
	}
	return FALSE;
}

