<?php
/***************************************************************************
 * File: lib.zones.php                                   Part of homeautod *
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

function load_zones($db) {
	$zones = $db->getdata("SELECT * FROM zones");
	return $zones;
}

function get_zone_index($id) {
	global $g_zones;
	foreach($g_zones as $n => $v) {
		if($v['id'] == $id) {
			return $n;
		}
	}
	throw new Exception("Unable to find zone with id {$id}");
}

function get_zone($id) {
	global $g_zones;
	foreach($g_zones as $v) {
		if($v['id'] == $id) {
			return $v;
		}
	}
	throw new Exception("Unable to find zone with id {$id}");
}

function get_zone_armed($id) {
	global $g_zones;
	foreach($g_zones as $v) {
		if($v['id'] == $id) {
			if(!empty($v['armed']))
				return TRUE;
			else
				return FALSE;
		}
	}
	throw new Exception("Unable to find zone with id: $id");
}

function arm_zone($id, $status) {
	global $database, $logger, $g_zones;

	if(!empty($status))
		$status = 1;
	else
		$status = 0;

	$i = get_zone_index($id);
	$logger->log(LOG_INFO, sprintf("%s zone {$g_zones[$i]['name']} ({$id})",
		($status==1?'Arming':'Disarming')));
	$g_zones[$i]['armed'] = $status;
	$database->setdata("UPDATE `zones` SET armed = '{$status}' WHERE id = '{$g_zones[$i]['id']}'");
}
