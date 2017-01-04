<?php
/***************************************************************************
 * File: lib.drivers.php                                 Part of homeautod *
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

require_once('lib.had_drv_class.php');

function load_driverlist() {
	$ret = array();
	set_include_path(HAD_HOME_PATH);
	$dlist = scandir(HAD_HOME_PATH . '/drv');
	foreach($dlist as $d) {
		if(preg_match("/^had_drv_([a-zA-Z0-9_-]+)\.php$/", $d, $m)) {
			$drvname = $m[1];
			$classname = "had_drv_{$drvname}";
			$ret[$drvname] = array();
			require_once("drv/{$d}");
			$drv = new $classname(null, null, null, null);
			$ret[$drvname]['ep'] = $drv->get_ep();
		}
	}
	return $ret;
}
