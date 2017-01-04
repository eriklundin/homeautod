<?php
/***************************************************************************
 * File: lib.schedules.php                               Part of homeautod *
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

function load_schedules() {
	global $database;
	return $database->getdata('SELECT * FROM schedules');
}

function find_schedule_by_id($id) {
	global $g_schedules;
	foreach($g_schedules as $s) {
		if($s['id'] == $id)
			return $s;
	}
	return FALSE;
}

function check_schedule($id, $ep = NULL) {
	global $logger, $g_schedules, $g_zones;

	if($id == HAD_SCHEDULE_ALWAYS)
		return TRUE;

	if($id == HAD_SCHEDULE_ZONE_ARMED) {
		$zone = get_zone($ep['zone_id']);
		if($zone['armed'] != 1) {
			$logger->log(LOG_DEBUG, "Ignoring event trigger on endpoint '{$ep['name']}' because zone '{$zone['name']}' is not armed");
			return FALSE;
		}
		return TRUE;
	}

	// We have a custom schedule
	if(($s = find_schedule_by_id($id)) === FALSE) {
		$logger->log(LOG_WARNING, "Unable to find schedule with id {$id}");
		return FALSE;
	}

	$t = time();
	$ref = array(
		'second' => intval(date('s', $t)),
		'minute' => intval(date('i', $t)),
		'hour' => intval(date('H', $t)),
		'day' => intval(date('j', $t)),
		'month' => intval(date('n', $t)),
		'weekday' => intval(date('w', $t)),
		'year' => intval(date('Y', $t))
	);

	foreach($ref as $n => $v) {

		if($s[$n] == '*')
			continue;

		$vals = explode(',', $s[$n]);

		$match = FALSE;
		foreach($vals as $e) {
			if(preg_match("/^[0-9]+$/", $e)) {
				if($e == $v) {
					$match = TRUE;
					continue;
				}
			} else if(preg_match("/^([0-9]+)-([0-9]+)$/", $e, $m)) {
				if($v >= $m[1] && $v <= $m[2]) {
					$match = TRUE;
					continue;
				}
			} else if(preg_match("/\*\/([0-9]+)(\+([0-9]+))?$/", $e, $m)) {
				$c = 0;
				if(isset($m[3]))
					$c = $m[3];
				if($v % $m[1] == $c) {
					$match = TRUE;
					continue;
				}
			} else {
				$logger->log(LOG_WARNING, "Invalid schedule value: $e");
			}
		}

		if($match === FALSE)
			return FALSE;

	}

	return TRUE;
}

function find_in_shed_queue($id) {
	global $g_shedqueue;
	foreach($g_shedqueue as $q) {
		if($q['endpoint_id'] == $id)
			return $q;
	}
	return FALSE;
}

function remove_from_schedqueue($id) {
	global $g_shedqueue;
	foreach($g_shedqueue as $n => $v) {
		if($v['endpoint_id'] == $id) {
			unset($g_shedqueue[$n]);
			$g_shedqueue = array_values($g_shedqueue);
			return;
		}
	}
}
