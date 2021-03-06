<?php
/***************************************************************************
 * File: lib.users.php                                   Part of homeautod *
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

function load_users() {
	global $database;
	return $database->getdata('SELECT id,name,username,mobile,email FROM users');
}

function find_user_by_id($id) {
	global $g_users;

	if(is_null($id))
		return FALSE;

	foreach($g_users as $u) {
		if($u['id'] == $id)
			return $u;
	}
	return FALSE;
}
