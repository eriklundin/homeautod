<?php
/***************************************************************************
 * File: lib.rtp.php                                     Part of homeautod *
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

define('RTP_FRAME_TYPE_P',	5);
define('RTP_FRAME_TYPE_I',	7);

class rtp {

	private $framebuffer = array();
	private $livebuffer = '';
	private $frame = '';
	private $logger = NULL;
	private $outfile = NULL;
	private $filename = NULL;
	private $min_frames = 30; // Number of frames to keep in the buffer
	private $seqnum = -1;

	public function start_dump($filename, $sprop) {

		$this->logger->log(LOG_INFO, "Starting data dump to file '{$filename}'");

		// Start writing to disk
		$fheader = pack('CCCC', 0x00, 0x00, 0x00, 0x01) .
			$sprop['sprop1'] .
			pack('CCCC', 0x00, 0x00, 0x00, 0x01) .
			$sprop['sprop2'];

		if(($this->outfile = fopen($filename, 'a')) === FALSE)
				throw new Exception("Unable to open {$filename} for writing");

		if(fwrite($this->outfile, $fheader) === FALSE)
			throw new Exception("Unable to write to file {$filename}");

		// Write all frames in the framebuffer
		foreach($this->framebuffer as $f) {
			fwrite($this->outfile, $f['frame']);
			foreach($f['pframes'] as $p) {
				if(fwrite($this->outfile, $p['frame']) === FALSE)
					throw new Exception("Unable to write to file {$filename}");
			}
		}
	}

	public function stop_dump() {
        if(!empty($this->outfile))
            fclose($this->outfile);
		$this->outfile = NULL;
	}

    private function handle_frame($frame) {

		// We need to figure out the slice type
		$header = substr($frame, 5, 6);
		$f = unpack('Cb1/Cb2', $header);
		$type = $this->find_slice_type($f['b1'], $f['b2']);

		if(!is_null($this->outfile))
			fwrite($this->outfile, $frame);

		$this->add_frame_to_buffer($type, $frame);
	}

	private function add_frame_to_buffer($type, $frame) {

		if($type == RTP_FRAME_TYPE_I) {
			// We got an I-Frame. Add it to the main level
			$this->framebuffer[] = array(
				'type' => $type,
				'frame' => $frame,
				'pframes' => array()
			);

			// Check if we need to cleanup the frame buffer
			$fcount = 0;
			for($i = count($this->framebuffer)-1; $i >= 0; $i--) {
				if($fcount >= $this->min_frames) {
					// We have all the frames we need saved.
					//$this->logger->log(LOG_DEBUG, "Clearing out " . (count($this->framebuffer[$i]['pframes']) + 1) . " frames from framebuffer");
					unset($this->framebuffer[$i]);
					$this->framebuffer = array_values($this->framebuffer);
				} else {
					$fcount += count($this->framebuffer[$i]['pframes']) + 1;
				}
			}

		} else if($type == RTP_FRAME_TYPE_P) {
			$len = count($this->framebuffer);
			$this->framebuffer[$len-1]['pframes'][] = array(
				'type' => $type,
				'frame' => $frame
			);
		}

		// Add the frame to the livestream data
		//$this->livebuffer .= $frame;
	}

	public function get_livebuffer() {

		if(empty($this->livebuffer))
			return '';

		$ret = $this->livebuffer;
		$this->livebuffer = '';
		return $ret;
	}

	private function find_slice_type($b1, $b2) {

		$pos = 0;
		$leadzero = 0;
		$value = 0;
		$valuecount = 0;

		$binstr = sprintf("%b%b", $b1, $b2);
		$len = strlen($binstr);

		while($pos<$len) {
			if(substr($binstr, $pos, 1) == 1) {
				if($leadzero == 0) {
					$value = 0;
				} else {
					$leadzero++;
					$value = bindec(substr($binstr, $pos, $leadzero)) - 1;
					if($valuecount == 1)
						return $value;
				}

				$pos += $leadzero;
				$leadzero = 0;
				$valuecount++;
			} else {
				$leadzero++;
			}
			$pos++;
		}
		$this->logger->log(LOG_WARNING, "Unable to find slice type: {$binstr}");
	}

	public function decode_frame($data) {

		$p = unpack('Cvpxcc/Cmpt/nseqnum/Ntimestamp/Nssrc', $data);
		$p['rtpversion'] = ($p['vpxcc'] & 0xC0) >> 6;
		$p['padding'] = ($p['vpxcc'] & 0x20) >> 5;
		$p['extension'] = ($p['vpxcc'] & 0x10) >> 4;
		$p['csrc_count'] = ($p['vpxcc'] & 0x0F) >> 0;
		$p['marker'] = ($p['mpt'] & 0x80) >> 7;
		$p['payload_type'] = ($p['mpt'] & 0x7F) >> 0;

		// Skip RTCP conflict avoidance frames
		if($p['payload_type'] >= 72 && $p['payload_type'] <= 76)
			return;

		if($this->seqnum == -1) {
			// Save the first sequence number
			$this->seqnum = $p['seqnum'];
		} else if($this->seqnum != $p['seqnum']) {
			$this->logger->log(LOG_WARNING, "Missed " . ($p['seqnum'] - $this->seqnum). " frames (Expected {$this->seqnum} but got {$p['seqnum']})");
		}

		if($p['seqnum'] == 65535) {
			// Wrap the sequence number
			$this->seqnum = 0;
		} else {
			$this->seqnum = $p['seqnum'] + 1;
		}

		// Cut out the header
		$data = substr($data, 12);

		for($i = 0; $i < $p['csrc_count']; $i++) {
			$csrc = unpack('N', $data);
			$data = substr($data, 4);
		}

		if($p['extension']) {
			$tmp = unpack('nt/nlen', $data);
			$data = substr($data, ($tmp['len']+1)*4);
		}

		if($p['padding']) {
			$p['padding'] = unpack('C', substr($data, -1, 1));
		}

		$len = strlen($data) - $p['padding'];
		$payload = substr($data, 0, $len);
		$p = array_merge($p, unpack('Cnal/Cfuheader', $data));

		$p['nal_f'] = ($p['nal'] & 0x80) >> 7;
		$p['nal_nri'] = ($p['nal'] & 0x60) >> 5;
		$p['nal_type'] = ($p['nal'] & 0x1F) >> 0;

		// For fragmented packets
		$p['fu_s'] = ($p['fuheader'] & 0x80) >> 7;
		$p['fu_e'] = ($p['fuheader'] & 0x40) >> 6;
		$p['fu_r'] = ($p['fuheader'] & 0x20) >> 5;
		$p['fu_type'] = ($p['fuheader'] & 0x1F) >> 0;

		// Single NAL
		if($p['nal_type'] == 1) {

			$this->frame = pack('CCCC', 0x00, 0x00, 0x00, 0x01) . $data;
			$this->handle_frame($this->frame);
			$this->frame = '';

		// FU-A (Fragmented)
		} else if($p['nal_type'] == 28) {

			$data = substr($data, 2);

			// Start of frame
			if($p['fu_s'] == 1) {
				$this->frame = pack('CCCC', 0x00, 0x00, 0x00, 0x01);
				$this->frame .= pack('C', ($p['nal'] & 0xE0) | ($p['fuheader'] & 0x1F));
			}

			$this->frame .= $data;

			// End of frame
			if($p['fu_e'] == 1) {
				$this->handle_frame($this->frame);
				$this->frame = '';
			}

		// TODO: Add support for more frame types?

		} else {
			$this->logger->log(LOG_WARNING, "Unknown nal type: {$p['nal_type']}");
		}
	}


	function __construct($logger) {
		$this->logger = $logger;
	}

}
