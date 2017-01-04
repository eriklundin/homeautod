<?php
/***************************************************************************
 * File: lib.database.php                                Part of homeautod *
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

require_once('lib.validate.php');

class database {

	private $link = NULL;
	private $type = NULL;
	private $server = NULL;
	private $user = NULL;
	private $password = NULL;
	private $logger = NULL;
	private $database = NULL;
	private $retries = 0;
	private $max_retries = 5;

	public function save($table, $indata, $params) {

		// Id is always optional
		$params['id'] = array('type' => 'int');

		$val = validate_indata($indata, $params);

		if(empty($val))
			throw new Exception('There was no valid data');

		if(empty($val['id'])) {
			// New item
			$cols = '';
			$values = '';
			foreach($val as $n => $v) {
				if(!empty($cols)) {
					$cols .= ',';
					$values .= ',';
				}
				$cols .= "`{$n}`";

				if($v == 'NOW()')
					$values .= $v;
				else
					$values .= "'" . $this->escapestring($v) . "'";
			}

			$sql = "INSERT INTO `{$table}`({$cols}) VALUES({$values})";

		} else {
			// Updating an existing item
			$values = '';
			foreach($val as $n => $v) {

				// Never update the id
				if($n == 'id')
					continue;

				if(!empty($values))
					$values .= ',';

				if($v == 'NOW()')
					$values .= "`{$n}` = $v";
				else
					$values .= "`{$n}` = '" . $this->escapestring($v) . "'";
			}

			$sql = "UPDATE `{$table}` SET {$values} WHERE id = '{$val['id']}'";
		}

		$this->setdata($sql);
		$id = $this->getlastinsertid();
		return $this->getdata("SELECT * FROM {$table} WHERE id = '{$id}'", true);
	}

	public function __construct($type, $server, $user, $password, $database, $logger) {

		switch($type) {
			case 'mysql':
				$this->type = $type;
			break;
			default:
				throw new Exception("Unknown database type '{$type}'");
		}

		if(empty($database))
			throw new Exception("No database was provided");

		$this->server = $server;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;
		$this->logger = $logger;

		$this->open_database();
	}

	private function open_database() {

		if(!is_null($this->link))
			unset($this->link);

		switch($this->type) {

			case 'mysql':

				$this->link = @mysqli_connect(
					$this->server,
					$this->user,
					$this->password,
					$this->database
				);

				if(mysqli_connect_error()) {
					throw new Exception("Unable to connect to database (" . mysqli_connect_errno() . "): " .
						mysqli_connect_error());
				}

				$this->logger->log(LOG_DEBUG, "Connected to mysql database on {$this->server}");
				mysqli_set_charset($this->link, 'utf8');

			break;
		}
	}

	public function getdata($query, $onlyfirst = false) {

		if(empty($query))
			throw new Exception('Database query was empty');

		if(!is_string($query))
			throw new Exception('Database query was not a string');

		$ret = array();

		start_getdata:

		if(($res = mysqli_query($this->link, $query)) === false) {
			$errno = mysqli_errno($this->link);
			switch($errno) {
				case 2006:
					// Reconnect to the database
					$this->cleanup();
					goto start_getdata;
				default:
					throw new Exception("Unable to query database: " . mysqli_error($this->link) . " (" . mysqli_errno($this->link) . ")");
			}
		}

		if($onlyfirst) {
			// Only return the first row
			$ret = mysqli_fetch_assoc($res);
		} else {
			while($row = mysqli_fetch_assoc($res)) {
				$ret[] = $row;
			}
		}

		mysqli_free_result($res);
		$this->retries = 0;
		return $ret;
	}

	public function getlastinsertid() {
		switch($this->type) {
			case 'mysql':
				return mysqli_insert_id($this->link);
		}
	}

	public function setdata($query) {
		start_setdata:
		if(($res = mysqli_query($this->link, $query)) === false) {
			$errno = mysqli_errno($this->link);
			switch($errno) {
				case 2006:
					// Reconnect to the database
					$this->reconnect();
					goto start_setdata;
				default:
					throw new Exception("Unable to query database: " . mysqli_error($this->link) . " (" . mysqli_errno($this->link) . ")");
			}
		}

		$this->retries = 0;
	}

	public function escapestring($str) {
		switch($this->type) {
			case 'mysql':
				return mysqli_real_escape_string($this->link, $str);
		}
	}

	private function cleanup() {
		switch($this->type) {
			case 'mysql':
				mysqli_close($this->link);
			break;
		}
	}

	private function reconnect() {

		if($this->retries >= $this->max_retries)
			throw new Exception("Giving up after {$this->retries} retries...");

		$this->cleanup();
		$this->open_database();
		$this->retries++;
	}

	public function __destruct() {
		$this->cleanup();
	}

}


