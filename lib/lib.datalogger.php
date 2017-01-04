<?php
/***************************************************************************
 * File: lib.datalogger.php                              Part of homeautod *
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

class datalogger {

	private $filename = NULL;
	private $rrdbin = NULL;
	private $dsname = NULL;
	private $interval = NULL;
	private $type = NULL;


	/**
	 */
	public function __construct($filename, $logger, $dsname, $interval, $minvalue, $maxvalue, $type = 'GAUGE') {

		$this->filename = "{$filename}.rrd";
		$this->dsname = $dsname;
		$this->interval = $interval;
		$this->type = $type;

		exec('which rrdtool', $output, $retval);
		if($retval != 0) {
			$logger->log(LOG_WARNING, "Unable to create datalogger since rrdtool is not installed");
			return;
		}

		$this->rrdbin = $output[0];
		if(!file_exists($this->filename)) {
			
			$cmd = "{$this->rrdbin} create {$this->filename} ".
				"--step {$interval} ".
				"DS:{$dbname}:{$this->type}:XXX:{$minvalue}:{$maxvalue} ".
				"RRA:MAX:0.5:1:288";
		}

	}

	/** Log data */
	public function log($value) {
		exec("{$this->rrdbin} update {$this->filename} N:{$value}", $output, $retval);
	}

}
