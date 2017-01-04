<?php
/***************************************************************************
 * File: lib.events.php                                  Part of homeautod *
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

function load_events($db) {

	$events = $db->getdata("SELECT * FROM events WHERE enabled = 1");

	foreach($events as $n => $v) {
		$events[$n]['triggers'] = $db->getdata("SELECT * FROM triggers WHERE event_id = '{$v['id']}'");
		$events[$n]['actions'] = $db->getdata("SELECT * FROM actions WHERE event_id = '{$v['id']}'");
	}

	return $events;
}

function find_event_by_id($id) {
	global $g_events;
	foreach($g_events as $e) {
		if($e['id'] == $id)
			return $e;
	}
	return FALSE;
}
