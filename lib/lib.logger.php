<?php
/***************************************************************************
 * File: lib.logger.php                                  Part of homeautod *
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

class logger {

	private $name = NULL;
	private $stdout = false;
	private $color = NULL;
	private $debug = false;

	public $function = '';
	public $user = '';
	public $remote_ip = '';

	function __construct($name) {
		$this->name = $name;
	}

	public function log($p, $txt, $prefixarr = array()) {

		$ftxt = '';

		foreach($prefixarr as $n => $v)
			$fxt .= "{$n}:[{$v}] ";
		
		if(!empty($this->remote_ip)) { $ftxt .= "IP:[{$this->remote_ip}] "; }
		if(!empty($this->user)) { $ftxt .= "U:[{$this->user}] "; }
		if(!empty($this->function)) { $ftxt .= "F:[{$this->function}] "; }

		$ftxt .= $txt;

		switch($p) {
			case LOG_DEBUG:
				$ptxt = 'DEBUG';
				if($this->debug)
					$p = LOG_INFO;
			break;
			case LOG_WARNING:
				$ptxt = 'WARNING';
			break;
			case LOG_CRIT:
				$ptxt = 'CRITICAL';
			break;
			case LOG_INFO:
				$ptxt = 'INFO';
			break;
		}


		if($this->stdout === true) {

			echo "{$ptxt}: ";
			if(!is_null($this->color))
				echo chr(27) . $this->color;

			echo $ftxt;

			if(!is_null($this->color))
				echo chr(27) . '[0m';

			echo "\n";

			return;
		}


		if(openlog($this->name,  LOG_PID | LOG_CONS, LOG_LOCAL0) === FALSE)
			throw new Exception("Unable to openlog()");

		if(syslog($p, "{$ptxt}: $ftxt") === FALSE)
			throw new Exception("Unable to syslog()");

		closelog();
	}

	public function stdout($val) {
		$this->stdout = $val;
	}

	public function debug($val) {
		$this->debug = $val;
	}

	public function set_color($color) {

		switch($color) {
			case 'red':
				$this->color = '[31m';
			break;
			case 'green':
				$this->color = '[32m';
			break;
			case 'yellow':
				$this->color = '[33m';
			break;
			default:
				throw new Exception("Unknown logger color: $color");
		}

	}

}

