<?php
/***************************************************************************
 * File: lib.had_drv_class.php                           Part of homeautod *
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

class had_drv_class {

	protected $path = NULL;
	protected $devdatapath = NULL;
	protected $devid = NULL;
	protected $a_read = array();
	protected $a_write = array();
	protected $a_except = array();
	protected $logger = NULL;
	protected $ep = array();
	protected $settings = array();
	public $is_init = false;

	function __construct($path, $devid, $devdatapath, $logger) {
		$this->path = $path;
		$this->devid = $devid;
		$this->devdatapath = $devdatapath;
		$this->logger = $logger;
	}

	public function init() {
	}

	public function get_select() {
		return array(
			'a_read' => array(),
			'a_write' => array(),
			'a_except' => array()
		);
	}

	public function set_select($a_read, $a_write, $a_except) {
		$this->a_read = $a_read;
		$this->a_write = $a_write;
		$this->a_except = $a_except;
	}

	public function set_data($cmd, $data) {
	}

	public function get_data() {
	}

	public function deinit() {
	}

	public function get_ep() {
		return $this->ep;
	}

	public function set_settings($data) {

		if(!empty($this->settings))
			$this->settings = array_merge($this->settings, $data);
		else
			$this->settings = $data;

		// Set the default timezone
		if(!empty($this->settings['timezone']))
			date_default_timezone_set($this->settings['timezone']);

	}

}
