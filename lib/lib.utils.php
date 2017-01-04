<?php
/***************************************************************************
 * File: lib.utils.php                                   Part of homeautod *
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

function bin2hexstring($data, $separator = ' ') {
	$ret = '';
	$len = strlen($data);
	for($i = 0; $i < $len; $i++) {
		if($i > 0)
			$ret .= $separator;
		$ret .= bin2hex($data[$i]);
	}
	return $ret;
}

function bin2array($val, $max, $num_bits) {
	$max = strlen(base_convert($max, 10, 2));
	$ret = array();
	$bin = base_convert($val, 10, 2);
	$bin = str_pad($bin, $max, '0', STR_PAD_LEFT);

	for($i = $max; $i > 0; $i--)
		$ret[] = $bin[$i-1];

	for($i = $max; $i > $num_bits; array_pop($ret), $i = count($ret));

	return $ret;
}

function create_had_packet($data) {
	$jdata = json_encode($data);
	return base64_encode($jdata);
}

function decode_had_packet($data) {
	$bdata = base64_decode($data);
	return json_decode($bdata, 1);
}

function interpret_indata($logger, &$data) {

	// See if there is any data to process at all.
	if(($pos = strpos($data, "\n")) === false)
		return false;

	$bdata = substr($data, 0, $pos);
	$data = substr($data, $pos + 1);

	if(strlen(trim($bdata)) === 0)
		return false;

	if(!preg_match("/^([A-Za-z0-9_-]+):(.*)$/", $bdata, $m)) {
		$logger->log(LOG_WARNING, "Invalid indata: $bdata");
		return false;
	}

	$ret = array(
		'cmd' => $m[1],
		'data' => decode_had_packet($m[2])
	);

	return $ret;
}

function strlen_utf8($str) {
	$ret = 0;
	$len = strlen($str);
	for ($i = 0; $i < $len; $i++) {
		if($str[$i] == "\xc3") {
				$i++;
		}
		$ret++;
	}
	return $ret;
}

function str_split_utf8($str) {
	$ret = array();
	$len = strlen($str);
	for ($i = 0; $i < $len; $i++) {
		if($str[$i] == "\xc3") {
				$ret[] = $str[$i] . $str[$i+1];
				$i++;
		} else
			$ret[] = $str[$i];
	}
	return $ret;
}

function str_pad_utf8($str, $plen, $ptxt) {
	$len = strlen_utf8($str);
	if($len < $plen) {
		for($i = 0; $i < $plen - $len; $i++)
			$str .= $ptxt;
	}
	return $str;
}

function substr_utf8($str, $start, $sublen = NULL) {
	$ret = '';

	$len = strlen($str);
	$ulen = strlen_utf8($str);

	if($start > $ulen)
		return '';

	if(is_null($sublen))
		$sublen = $ulen - $start;

	// Don't start in the middle of the ctrl-char
	if($start > 0 && ord($str[$start - 1]) == 0xc3)
		$start++;

	$glen = 0;
	for($i = $start; $i < $len; $i++) {

		if(ord($str[$i]) == 0xc3) {
			$ret .= $str[$i];
			$ret .= $str[$i+1];
			$i++;
		} else {
			$ret .= $str[$i];
		}

		$glen++;
		if($glen == $sublen)
			break;
	}

	return $ret;
}
