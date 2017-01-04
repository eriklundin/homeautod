<?php
/***************************************************************************
 * File: lib.h264.php                                    Part of homeautod *
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

require_once('lib.utils.php');

class h264 {

	public static function decode_sps($data) {
		return self::handle_sps($data, true);
	}

	public static function encode_sps($data) {
		return self::handle_sps($data, false);
	}

	public static function parse_h264_file($filename) {

		$startcode = chr(0x00) . chr(0x00) . chr(0x00) . chr(0x01);

		if(!file_exists($filename))
			throw new Exception("The file $filename doesn't exist");

		if(($fh = fopen($filename, 'r')) === FALSE)
			throw new Exception("Unable to open $filename");

		$ret = array(
			'num_frames' => 0
		);

		$buffer = '';
		while(!feof($fh)) {

			$buffer .= fread($fh, 2048);
			if(($pos = strpos($buffer, $startcode)) !== FALSE) {

				$fhead = substr($buffer, $pos + 4, 1);
				$type = (ord($fhead) & 0x1F) >> 0;

				switch($type) {
					case 1:
					case 5:
						$ret['num_frames']++;
					break;
					case 7:

						while(($npos = strpos($buffer, $startcode, $pos + 4)) === FALSE) {
							$buffer .= fread($fh, 32);

							// Have we reached the end of the file without getting the sps?
							if(feof($fh)) {
								$npos = strlen($buffer);
								break;
							}
						}

						$ret['sps'] = substr($buffer, $pos + 4, $npos - $pos - 4);
						$sps = self::decode_sps($ret['sps']);
						print_r($sps);

						$ret['profile_idc'] = chr($sps['profile_idc']);
						$ret['level_idc'] = chr($sps['level_idc']);
						$ret['nal_ref_idc'] = $sps['nal_ref_idc'];
						$ret['width'] = ($sps['pic_width_in_mbs_minus1'] + 1) * 16;
						$ret['height'] = ($sps['pic_height_in_map_units_minus1'] + 1) * 16;

						if(!empty($sps['frame_cropping_flag'])) {
							$ret['width'] -= ($sps['frame_crop_left_offset'] * 2);
							$ret['width'] -= ($sps['frame_crop_right_offset'] * 2);
							$ret['height'] -= ($sps['frame_crop_top_offset'] * 2);
							$ret['height'] -= ($sps['frame_crop_bottom_offset'] * 2);
						}

						$ret['fps'] = ($sps['vui_time_scale'] / $sps['vui_num_units_in_tick']) / 2;

					break;
					case 8:
						while(($npos = strpos($buffer, $startcode, $pos + 4)) === FALSE) {
							$buffer .= fread($fh, 32);

							// Have we reached the end of the file without getting the sps?
							if(feof($fh)) {
								$npos = strlen($buffer);
								break;
							}
						}
						$ret['pps'] = substr($buffer, $pos + 4, $npos - $pos - 4);
					break;
				}

				$buffer = substr($buffer, $pos + 4);
			}

		}

		fclose($fh);
		return $ret;

	}


	private static function binstr_to_bin($str) {
		$len = strlen($str);
		$ret = '';
		for($i = 0; $i < $len; $i += 8) {
			if($i + 8 > $len)
				$str .= str_pad('1', (($i + 8) - $len), '0', STR_PAD_RIGHT) . "\n";
			$ret .= chr(bindec(substr($str, $i, 8)));
		}
		return $ret;
	}

	private static function bin_to_binstr($data) {
		$len = strlen($data);
		$binstr = '';
		for($i = 0; $i < $len; $i++) {
			$binstr .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
		}
		return $binstr;
	}

	private static function handle_bits(&$varr, &$binstr, $name, $len, $decode) {
		if($decode) {
			$varr[$name] = bindec(substr($binstr, 0, $len));
			$binstr = substr($binstr, $len);
		} else {
			$binstr .= str_pad(decbin($varr[$name]), $len, '0', STR_PAD_LEFT);
		}
	}

	private static function handle_ue(&$varr, &$binstr, $name, $decode) {
		if($decode) {
			$len = strlen($binstr);
			for($i = 0; $i < $len; $i++) {
				if($binstr[$i] != 0) {
					$r = substr($binstr, $i, $i + 1);
					if(($binstr = substr($binstr, ($i * 2) + 1)) === FALSE)
						$binstr = '';
					$varr[$name] = (bindec($r) - 1);
					break;
				}
			}
		} else {
			$b = decbin($varr[$name] + 1);
			$binstr .= str_pad($b, (strlen($b) * 2) - 1, '0', STR_PAD_LEFT);
		}
	}

	private static function handle_hrd($prefix, &$varr, &$binstr, $decode) {
		self::handle_ue($varr, $binstr, "{$prefix}_hrd_cpb_cnt_minus1", $decode);		
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_bit_rate_scale", 4, $decode);
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_cpb_size_scale", 4, $decode);
		for($i = 0; $i <= $varr["{$prefix}_hrd_cpb_cnt_minus1"]; $i++) {
			self::handle_ue($varr, $binstr, "{$prefix}_hrd_bit_rate_value_minus1_$i", $decode);		
			self::handle_ue($varr, $binstr, "{$prefix}_hrd_cpb_size_value_minus1_$i", $decode);		
			self::handle_bits($varr, $binstr, "{$prefix}_hrd_cbr_flag_$i", 1, $decode);
		}
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_initial_cpb_removal_delay_length_minus1", 5, $decode);
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_cpb_removal_delay_length_minus1", 5, $decode);
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_dpb_output_delay_length_minus1", 5, $decode);
		self::handle_bits($varr, $binstr, "{$prefix}_hrd_time_offset_length", 5, $decode);
	}

	private static function handle_vui(&$varr, &$binstr, $decode) {

		self::handle_bits($varr, $binstr, 'vui_aspect_ratio_info_present_flag', 1, $decode);		
		if($varr['vui_aspect_ratio_info_present_flag']) {
			self::handle_bits($varr, $binstr, 'vui_aspect_ratio_idc', 8, $decode);		
			if($varr['vui_aspect_ratio_idc'] == 255) { // Extended_SAR
				self::handle_bits($varr, $binstr, 'vui_sar_width', 16, $decode);		
				self::handle_bits($varr, $binstr, 'vui_sar_height', 16, $decode);		
			}
		}

		self::handle_bits($varr, $binstr, 'vui_overscan_info_present_flag', 1, $decode);
		if($varr['vui_overscan_info_present_flag'])
			self::handle_bits($varr, $binstr, 'vui_overscan_appropriate_flag', 1, $decode);

		self::handle_bits($varr, $binstr, 'vui_video_signal_type_present_flag', 1, $decode);
		if($varr['vui_video_signal_type_present_flag']) {
			self::handle_bits($varr, $binstr, 'vui_video_format', 3, $decode);	
			self::handle_bits($varr, $binstr, 'vui_video_full_range_flag', 1, $decode);
			self::handle_bits($varr, $binstr, 'vui_colour_description_present_flag', 1, $decode);
			if($varr['vui_colour_description_present_flag']) {
				self::handle_bits($varr, $binstr, 'vui_colour_primaries', 8, $decode);	
				self::handle_bits($varr, $binstr, 'vui_transfer_characteristics', 8, $decode);	
				self::handle_bits($varr, $binstr, 'vui_matrix_coefficients', 8, $decode);	
			}
		}

		self::handle_bits($varr, $binstr, 'vui_chroma_loc_info_present_flag', 1, $decode);
		if($varr['vui_chroma_loc_info_present_flag']) {
			self::handle_ue($varr, $binstr, 'vui_chroma_sample_loc_type_top_field', $decode);
			self::handle_ue($varr, $binstr, 'vui_chroma_sample_loc_type_bottom_field', $decode);
		}

		self::handle_bits($varr, $binstr, 'vui_timing_info_present_flag', 1, $decode);
		if($varr['vui_timing_info_present_flag']) {
			self::handle_bits($varr, $binstr, 'vui_num_units_in_tick', 32, $decode);
			self::handle_bits($varr, $binstr, 'vui_time_scale', 32, $decode);
			self::handle_bits($varr, $binstr, 'vui_fixed_frame_rate_flag', 1, $decode);
		}

		self::handle_bits($varr, $binstr, 'vui_nal_hrd_parameters_present_flag', 1, $decode);
		if($varr['vui_nal_hrd_parameters_present_flag'])
			self::handle_hrd('nal', $varr, $binstr, $decode);

		self::handle_bits($varr, $binstr, 'vui_vcl_hrd_parameters_present_flag', 1, $decode);
		if($varr['vui_vcl_hrd_parameters_present_flag'])
			self::handle_hrd('vcl', $varr, $binstr, $decode);

		self::handle_bits($varr, $binstr, 'vui_pic_struct_present_flag', 1, $decode);

		self::handle_bits($varr, $binstr, 'vui_bitstream_restriction_flag', 1, $decode);
		if($varr['vui_bitstream_restriction_flag']) {
			self::handle_bits($varr, $binstr, 'vui_motion_vectors_over_pic_boundaries_flag', 1, $decode);
			self::handle_ue($varr, $binstr, 'vui_max_bytes_per_pic_denom', $decode);
			self::handle_ue($varr, $binstr, 'vui_max_bits_per_mb_denom', $decode);
			self::handle_ue($varr, $binstr, 'vui_log2_max_mv_length_horizontal', $decode);
			self::handle_ue($varr, $binstr, 'vui_log2_max_mv_length_vertical', $decode);
			self::handle_ue($varr, $binstr, 'vui_num_reorder_frames', $decode);
			self::handle_ue($varr, $binstr, 'vui_max_dec_frame_buffering', $decode);
		}

	}

	private static function handle_sps($data, $decode) {

		if($decode) {
			$varr = array();
			$binstr = self::bin_to_binstr($data);
		} else {
			$varr = $data;
			$binstr = '';
		}

		self::handle_bits($varr, $binstr, 'forbidden_zero_bit', 1, $decode);
		self::handle_bits($varr, $binstr, 'nal_ref_idc', 2, $decode);
		self::handle_bits($varr, $binstr, 'nal_unit_type', 5, $decode);
		self::handle_bits($varr, $binstr, 'profile_idc', 8, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set0_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set1_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set2_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set3_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set4_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'constraint_set5_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'reserved_zero_2bits', 2, $decode);
		self::handle_bits($varr, $binstr, 'level_idc', 8, $decode);
		self::handle_ue($varr, $binstr, 'seq_parameter_set_id', $decode);

		if(in_array($varr['profile_idc'], array(100, 110, 122, 144))) {
			self::handle_ue($varr, $binstr, 'chroma_format_idc', $decode);
			if($varr['chroma_format_idc'] == 3) {
				self::handle_bits($varr, $binstr, 'residual_colour_transform_flag', 1, $decode);
			}
			self::handle_ue($varr, $binstr, 'bit_depth_luma_minus8', $decode);
			self::handle_ue($varr, $binstr, 'bit_depth_chroma_minus8', $decode);
			self::handle_bits($varr, $binstr, 'qpprime_y_zero_transform_bypass_flag', 1, $decode);
			self::handle_bits($varr, $binstr, 'seq_scaling_matrix_present_flag', 1, $decode);
			if($varr['seq_scaling_matrix_present_flag'])
				throw new Exception("seq_scaling_matrix_present_flag not supported");
		}

		self::handle_ue($varr, $binstr, 'log2_max_frame_num_minus4', $decode);

		self::handle_ue($varr, $binstr, 'pic_order_cnt_type', $decode);
		if($varr['pic_order_cnt_type'] == 0) {
			self::handle_ue($varr, $binstr, 'log2_max_pic_order_cnt_lsb_minus4', $decode);
		} else if($varr['pic_order_cnt_type'] == 1) {
			self::handle_bits($varr, $binstr, 'delta_pic_order_always_zero_flag', 1, $decode);
			//self::handle_se($varr, $binstr, 'offset_for_non_ref_pic', $decode);
			//self::handle_se($varr, $binstr, 'offset_for_top_to_bottom_field', $decode);
			self::handle_ue($varr, $binstr, 'num_ref_frames_in_pic_order_cnt_cycle', $decode);
		}

		self::handle_ue($varr, $binstr, 'num_ref_frames', $decode);
		self::handle_bits($varr, $binstr, 'gaps_in_frame_num_value_allowed_flag', 1, $decode);
		self::handle_ue($varr, $binstr, 'pic_width_in_mbs_minus1', $decode);
		self::handle_ue($varr, $binstr, 'pic_height_in_map_units_minus1', $decode);

		self::handle_bits($varr, $binstr, 'frame_mbs_only_flag', 1, $decode);
		if(!$varr['frame_mbs_only_flag'])
			self::handle_bits($varr, $binstr, 'mb_adaptive_frame_field_flag', 1, $decode);

		self::handle_bits($varr, $binstr, 'direct_8x8_inference_flag', 1, $decode);
		self::handle_bits($varr, $binstr, 'frame_cropping_flag', 1, $decode);
		if($varr['frame_cropping_flag']) {
			self::handle_ue($varr, $binstr, 'frame_crop_left_offset', $decode);
			self::handle_ue($varr, $binstr, 'frame_crop_right_offset', $decode);
			self::handle_ue($varr, $binstr, 'frame_crop_top_offset', $decode);
			self::handle_ue($varr, $binstr, 'frame_crop_bottom_offset', $decode);
		}

		self::handle_bits($varr, $binstr, 'vui_parameters_present_flag', 1, $decode);
		if($varr['vui_parameters_present_flag'])
			self::handle_vui($varr, $binstr, $decode);

		if($decode)
			return $varr;
		else
			return self::binstr_to_bin($binstr);

	}

}
