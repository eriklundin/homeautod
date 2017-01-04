<?php
/***************************************************************************
 * File: lib.triggers.php                                Part of homeautod *
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

function load_triggers($db) {
	$trig = $db->getdata("SELECT * FROM triggers WHERE enabled = 1");
	return $trig;
}

function check_trigger_value($value, $trigger) {
	global $logger;

	switch($trigger['rel_operator']) {
		case '<':
			if($value < $trigger['value'])
				return TRUE;
		break;
		case '>':
			if($value > $trigger['value'])
				return TRUE;
		break;
		case '<=':
			if($value <= $trigger['value'])
				return TRUE;
		break;
		case '>=':
			if($value >= $trigger['value'])
				return TRUE;
		break;
		case '==':
			if($value == $trigger['value'])
				return TRUE;
		break;
		default:
			$logger->log(LOG_WARNING, "Unknown relations operator: {$trigger['rel_operator']}");
	}
	return FALSE;
}


