<?php
/***************************************************************************
 * File: lib.queue.php                                   Part of homeautod *
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

function load_queue() {
	global $database, $g_queue;
	return $database->getdata('SELECT * FROM `queue`');
}

function add_to_queue($params) {
	global $g_queue, $database;

	$ret = $database->save('queue', $params,
		array(
			'event_id' => array('type' => 'int'),
			'endpoint_id' => array('type' => 'int'),
			'time_end' => array('type' => 'int')
		)
	);

	if(empty($params['id']))
		$g_queue[] = $ret;
}

function remove_from_queue($index) {
	global $g_queue, $database;
	$database->setdata("DELETE FROM `queue` WHERE id = '{$g_queue[$index]['id']}'");
	unset($g_queue[$index]);
	$g_queue = array_values($g_queue);
}

function find_queue_index($epnum) {
	global $g_queue;
	foreach($g_queue as $n => $v) {
		if($v['endpoint_id'] == $epnum)
			return $n;
	}
	return FALSE;
}
