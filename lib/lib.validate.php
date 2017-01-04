<?php
/***************************************************************************
 * File: lib.validate.php                                Part of homeautod *
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

function enum_regex($type) {
	global $g_drvlist;

	switch($type) {
		case 'username':
			return "/^[A-Za-z0-9_-]+$/";
		case 'password':
			return "/^.+$/";
		case 'int':
			return "/^[0-9]+$/";
		default:
			throw new Exception("Unknown regex '{$type}'");
	}
}


function validate_indata($data, $params) {
	global $g_drvlist;

	$ret = array();

	// Check for mandatory values
	foreach($params as $pn => $pv) {

		if(!array_key_exists($pn, $data)) {
			if(isset($pv['mandatory']) && $pv['mandatory'] === true) {
					throw new Exception("Mandatory value for $pn was not supplied");
			} else {
				continue;
			}
		}

		if(!array_key_exists('type', $pv)) 
			throw new Exception("The key '$pn' does not have a type defined");

		switch($pv['type']) {
			case 'int':
				if(!preg_match("/^\-?[0-9]+$/", $data[$pn]))
					throw new Exception("The key $pn has an invalid int value: '{$data[$pn]}'");
			break;
			case 'bool':
				if($data[$pn] === '1' || $data[$pn] === 1 || $data[$pn] === true)
					$data[$pn] = true;
				else
					$data[$pn] = false;
			break;
			case 'driver':
				if(!array_key_exists($data[$pn], $g_drvlist))
					throw new Exception("Invalid driver '{$data[$pn]}'");
			break;
			case 'string':
				// TODO: Fix string validation
			break;
			case 'date':
				// TODO: Add date validation including NOW()
			break;
			default:
				throw new Exception("Invalid validation type: {$pv['type']}");
		}

		$ret[$pn] = $data[$pn];
	}

	return $ret;
}
