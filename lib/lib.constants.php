<?php
/***************************************************************************
 * File: lib.constants.php                               Part of homeautod *
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

define('HAD_HOME_PATH',		'/usr/lib/homeautod');
define('HAD_DATA_PATH',		'/var/lib/homeautod');
define('HAD_DEV_DATA_PATH',	HAD_DATA_PATH . "/devicedata");
define('HAD_CMD_PIPE',		HAD_DATA_PATH . '/homeautod.cmd');
define('HAD_CFG_FILE',		'/etc/homeautod.conf');
define('HAD_PID_FILE',		'/var/run/homeautod.pid');

define('HAD_DEV_STATUS_ERROR',		-2);
define('HAD_DEV_STATUS_STOP',		-1);
define('HAD_DEV_STATUS_LOADED',		0);
define('HAD_DEV_STATUS_STARTED',	1);

define('HAD_EP_STATUS_LOW',			0);
define('HAD_EP_STATUS_HIGH',		1);
define('HAD_EP_STATUS_CHANGED',		2);

define('HAD_EP_DT_STATUS',			0);
define('HAD_EP_DT_VALUE',			1);
define('HAD_EP_DT_EVENT',			2);

define('HAD_DEV_RESTART_TIME',		5); // Restart the driver after 5 seconds

define('HAD_DRV_QUE_PKT_STAT_NEW',	1);
define('HAD_DRV_QUE_PKT_STAT_SENT',	2);

function get_default_schedule_name($i) {
	switch($i) {
		case -1:
			return 'Zone of endpoint is armed';
		case 0:
			return 'Always';
	}
}

define('HAD_SCHEDULE_ZONE_ARMED',	-1);
define('HAD_SCHEDULE_ALWAYS',		0);

define('PACKET_STATUS_NEW',		0);
define('PACKET_STATUS_SENT',		1);
