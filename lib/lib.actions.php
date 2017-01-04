<?php
/***************************************************************************
 * File: lib.actions.php                                 Part of homeautod *
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

function count_actions_trigger($id) {
	global $g_actions;
	$num = count($g_actions);
	if($num > 0)
		return TRUE;
	else
		return FALSE;
}

function log_action_run(&$f, $e, $t) {
	global $logger;
	if(!$f) {
		$ep = find_endpoint_by_id($t['endpoint_id']);
		$logger->log(LOG_INFO, "Actions in event {$e['name']} was triggered by endpoint {$ep['name']}");
		$f = true;
	}
}

function run_actions($event, $trigger) {

	global $g_devices, $g_queue, $logger, $g_shedqueue;

	$msgw = false; // Have we logged that the event is run?

	foreach($event['actions'] as $a) {

		$time_end = 0;

		if(($di = find_endpoint_by_id($a['endpoint_id'], TRUE)) === FALSE) {
			$logger->log(LOG_WARNING, "Unable to find device id for endpoint {$a['endpoint_id']} in action {$a['id']}");
			continue;
		}

		if(($e = find_endpoint_by_id($a['endpoint_id'])) === FALSE) {
			$logger->log(LOG_WARNING, "Unable to find endpoint {$a['endpoint_id']} in action {$a['id']}");
			continue;
		}

		if(!empty($trigger['schedule_id']) && find_in_shed_queue($e['id']) !== FALSE)
			continue;

		$qi = find_queue_index($a['endpoint_id']);

		if($e['status'] != $a['ep_status']) {

			log_action_run($msgw, $event, $trigger);

			$ntxt = '';
			if(($ntime = $a['min_time'] + $a['add_time']) > 0)
				$ntxt = " for {$ntime} seconds";

			$logger->log(LOG_INFO, "Setting endpoint {$e['name']} ({$e['epnumber']}) on device {$g_devices[$di]['name']} to status " . ep_status_txt($a['ep_status']) . $ntxt);

			$tep = find_endpoint_by_id($trigger['endpoint_id']);

			$data = array(array(
				'epnumber' => $e['epnumber'],
				'status' => $a['ep_status'],
				'event_name' => $event['name'],
				'device_name' => $g_devices[$di]['name'],
				'endpoint_name' => $e['name'],
				'trigger_endpoint_name' => $tep['name'],
				'user' => find_user_by_id($a['user_id'])
			));

			$g_devices[$di]['outbuf'] .= 'SETEPDATA:' . create_had_packet($data) . "\n";
			$time_end += $ntime;

			// Add to the schedule queue so we don't hammer the endpoint
			if(!empty($trigger['schedule_id'])) {
				$g_shedqueue[] = array(
					'event_id' => $event['id'],
					'schedule_id' => $trigger['schedule_id'],
					'endpoint_id' => $e['id'],
					'ep_status' => $a['ep_status']
					
				);
			}

		} else {		

			// Check if the queue needs to be prolonged
			if($qi !== FALSE && !empty($a['min_time'])) {
				$time_left = $g_queue[$qi]['time_end'] - time();
				if($time_left < $a['min_time']) {
					$timediff = ($a['min_time'] - $time_left);
					log_action_run($msgw, $event, $trigger);
					$logger->log(LOG_INFO, "Prolonging queue time for endpoint {$e['name']} ({$e['epnumber']}) on device {$g_devices[$di]['name']} with {$timediff} seconds");
					$time_end += $a['min_time'];
				}
			}

			if($qi !== FALSE && !empty($a['add_time'])) {
				log_action_run($msgw, $event, $trigger);
				$logger->log(LOG_INFO, "Prolonging queue time for endpoint {$e['name']} ({$e['epnumber']}) on device {$g_devices[$di]['name']} with {$a['add_time']} seconds");
				$time_end += $a['add_time'];
			}

		}

		// Handle queue update
		if(!empty($time_end)) {

			$nt = time();
			$qparams = array(
				'event_id' => $event['id'],
				'endpoint_id' => $e['id'],
				'time_end' => $nt + $time_end
			);

			if($qi !== FALSE) {
				$qparams['id'] = $g_queue[$qi]['id'];
				$g_queue[$qi]['event_id'] = $event['id'];
				$g_queue[$qi]['endpoint_id'] = $e['id'];
				$g_queue[$qi]['time_end'] = $nt + $time_end;
			}

			add_to_queue($qparams);
		}

	}
}
