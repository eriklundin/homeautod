<?php
/***************************************************************************
 * File: lib.mp4.php                                     Part of homeautod *
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

require_once('lib.h264.php');
require_once('lib.utils.php');

class mp4 {

	private function arr_to_box($arr) {
		$ret = '';
		foreach($arr as $n => $v) {
			if(is_array($v)) {
				$r = $this->arr_to_box($v);
				$len = strlen($r) + 8;
				$ret .= pack('N', $len) . $n . $this->arr_to_box($v);
			} else {
				$ret .= $v;
			}
		}
		return $ret;
	}

	public function create($filename) {

		$arr = array();
		$arr['ftyp'] = array(
			'major_brand' => 'isom',
			'minor_version' => pack('N', 0),
			'compat1' => 'isom',
			'compat2' => 'iso2',
			'compat3' => 'avc1',
			'compat4' => 'mp41'
		);

		// Get information about the file
		$hinfo = h264::parse_h264_file($filename);
		print_r($hinfo);

		echo "SPS: " . bin2hexstring($hinfo['sps']) . "\n";
		echo "PPS: " . bin2hexstring($hinfo['pps']) . "\n";

		$ctime = filectime($filename) + 2082844800;
		$mtime = filemtime($filename) + 2082844800;
		$duration = ($hinfo['num_frames'] / $hinfo['fps']) * 1000;

		$arr['moov']['mvhd'] =  array(
			'version' => chr(0),			// 1 byte
			'flags' => str_repeat(chr(0), 3),	// 3 bytes
			'ctime' => pack('N', $ctime),		// 4 bytes
			'mtime' => pack('N', $mtime),		// 4 bytes
			'time_scale' => pack('N', 1000),	// 4 bytes
			'duration' => pack('N', $duration),	// 4 bytes
			'pref_rate' => pack('N', 65536),	// 4 bytes
			'pref_volume' => str_repeat(chr(0), 2),	// 2 bytes
			'reserved' => str_repeat(chr(0), 10),	// 10 bytes
			'matrix' => str_repeat(chr(0), 36),	// 36 bytes
			'preview_time' => pack('N', 0),		// 4 bytes
			'preview_dur' => pack('N', 0),		// 4 bytes
			'poster_time' => pack('N', 0),		// 4 bytes
			'selection_time' => pack('N', 0),	// 4 bytes
			'selection_dur' => pack('N', 0),	// 4 bytes
			'current_time' => pack('N', 0),		// 4 bytes
			'next_track_id' => pack('N', 1)		// 4 bytes
		);

		$arr['moov']['trak']['tkhd'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0) . chr(0). chr(1),	// 3 bytes
			'ctime' => pack('N', $ctime),		// 4 bytes
			'mtime' => pack('N', $mtime),		// 4 bytes
			'track_id' => pack('N', 1),		// 4 bytes
			'reserved' => pack('N', 0),		// 4 bytes
			'duration' => pack('N', $duration),	// 4 bytes
			'reserved2' => str_repeat(chr(0), 8),	// 8 bytes
			'layer' => str_repeat(chr(0), 2),	// 2 bytes
			'agroup' => str_repeat(chr(0), 2),	// 2 bytes
			'volume' => str_repeat(chr(0), 2),	// 2 bytes
			'reserved3' => str_repeat(chr(0), 2),	// 2 bytes
			'matrix' => str_repeat(chr(0), 36),	// 36 bytes
			'twidth' => pack('N', $hinfo['width']),	// 4 bytes
			'theight' => pack('N', $hinfo['height'])// 4 bytes
		);

		$arr['moov']['trak']['mdia']['mdhd'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0) . chr(0). chr(0),	// 3 bytes
			'ctime' => pack('N', $ctime),		// 4 bytes
			'mtime' => pack('N', $mtime),		// 4 bytes
			'time_scale' => pack('N', 1000),	// 4 bytes
			'duration' => pack('N', $duration),	// 4 bytes
			'language' => str_repeat(chr(0), 2),	// 2 bytes
			'quality' => str_repeat(chr(0), 2)	// 2 bytes
		);

		$arr['moov']['trak']['mdia']['hdlr'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => str_repeat(chr(0), 3),	// 3 bytes
			'ctype'	=> str_repeat(chr(0), 4),	// 4 bytes
			'csubtype' => 'vide',			// 4 bytes
			'cmanuf' => str_repeat(chr(0), 4),	// 4 bytes
			'cflags' => str_repeat(chr(0), 4),	// 4 bytes
			'cfmask' => str_repeat(chr(0), 4),	// 4 bytes
			'cname' => 'VideoHandler' . chr(0)
		);

		$arr['moov']['trak']['mdia']['minf']['vmhd'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(1),	// 3 bytes
			'graph_mode' => str_repeat(chr(0), 2),	// 2 bytes
			'opcolor' => str_repeat(chr(0), 6)	// 6 bytes
		);

	//	$arr['moov']['trak']['mdia']['minf']['dinf']['dref'] = array(
	//	);

		$arr['moov']['trak']['mdia']['minf']['stbl']['stsd']['avc1']['avcC'] = array(
			'version' => chr(0),			// 1 byte
			'profile_idc' => $hinfo['profile_idc'],	// 1 byte
			'profile_compat' => chr(0x40),		// 1 byte
			'avc_lvl' => $hinfo['level_idc'],
			'lenminone' => chr(0xFC + $hinfo['nal_ref_idc']),
			'num_sps' => chr(0xe1),
			'sps_len' => pack('n', strlen($hinfo['sps'])),
			'sps' => $hinfo['sps'],
			'num_pps' => chr(1),
			'pps_len' => pack('n', strlen($hinfo['pps'])),
			'pps' => $hinfo['pps']
		);

		// Sample table time to sample map
		$arr['moov']['trak']['mdia']['minf']['stbl']['stts'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(0),	// 3 bytes
			'num_entr' => pack('N', 0)		// 4 bytes
		);

		// Sample table sync samples
		$arr['moov']['trak']['mdia']['minf']['stbl']['stss'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(0),	// 3 bytes
			'num_entr' => pack('N', 0)		// 4 bytes
		);

		// Sample table sample to chunk map
		$arr['moov']['trak']['mdia']['minf']['stbl']['stsc'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(0),	// 3 bytes
			'num_entr' => pack('N', 0)		// 4 bytes
		);

		// Sample table size atom
		$arr['moov']['trak']['mdia']['minf']['stbl']['stsz'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(0),	// 3 bytes
			'num_entr' => pack('N', 0)		// 4 bytes
		);

		$arr['moov']['trak']['mdia']['minf']['stbl']['stco'] = array(
			'version' => chr(0),			// 1 byte
			'flags' => chr(0), chr(0), chr(0),	// 3 bytes
			'num_entr' => pack('N', 0)		// 4 bytes
		);

		$arr['free'] = array();

		$fsize = filesize($filename);
		$mhead = $this->arr_to_box($arr) . pack('N', $fsize + 8) . 'mdat';
		return $mhead;
	}

}
