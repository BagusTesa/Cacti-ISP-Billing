<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2010 Cacti Group                                     |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | cacti: a php-based graphing solution                                    |
 +-------------------------------------------------------------------------+
 | This script uses a configuration file to process graphs for billing.    |
 | Only graphs with COMMENT items will be processed. Any questions         |
 | contact Tony Roman (roman@disorder.com).                                |
 +-------------------------------------------------------------------------+
 | - Cacti - http://www.cacti.net/                                         |
 +-------------------------------------------------------------------------+
*/

/*
*****************************************************************************
* Graph Processing Functions 
*****************************************************************************
 */

/* process_graph - Process requested graph and items
 *   @arg - $graph_id - Graph id to process
 *   @arg - $graph_item_ids - Graph items, empty means all that match
 *   @arg - $start_time - Start time
 *   @arg - $end_time - End time
 *   @return - array of values, no threshold key means no thresholds exceeded
 */
function process_graph($graph_id, $graph_item_ids, $start_time, $end_time) {

	$output = array();

	$where = "";
	if (!empty($graph_item_ids)) {
		$where .= " AND i.id IN(" . $graph_item_ids . ")";
	}
	$where .= " AND g.local_graph_id = " . $graph_id;

	$graph_items = db_fetch_assoc("SELECT 
			i.id AS item_id, 
			i.local_graph_id, 
			i.task_item_id, 
			i.text_format, 
			g.id AS graph_id, 
			g.title_cache,
			d.local_data_id,
			d.data_source_name
		FROM 
			graph_templates_graph AS g,
			graph_templates_item AS i LEFT JOIN data_template_rrd as d ON i.task_item_id = d.id 
		WHERE 
			i.local_graph_id = g.local_graph_id AND 
			i.local_graph_id <> 0 AND 
			i.graph_type_id = 1 AND 
			text_format LIKE '%|%:%:%|%'
			" . $where . "
		ORDER BY
			i.local_graph_id;");

	if (is_array($graph_items)) {
		if (sizeof($graph_items) > 0) {
			foreach ($graph_items as $graph_item) {
	
				$match = array();
				if (preg_match('/\|sum\:([0-9]+|auto)\:(current|total)\:([0-9]+)\:([0-9]+|auto)\|/',$graph_item["text_format"], $match) > 0) { 
					$output[$graph_item["item_id"]]["type"] = "Bandwidth Summation";
					$output[$graph_item["item_id"]]["value_bits"] = process_variables("|sum:0:" . $match[2] . ":0:auto|", $graph_item, $start_time, $end_time);
					$output[$graph_item["item_id"]]["var_bits"] = "|sum:0:" . $match[2] . ":0:auto|";
				} elseif (preg_match('/\|([0-9]{1,2})\:(\S+)\:([0-9]+)\:(\S+)\:([0-9]+)\|/',$graph_item["text_format"], $match) > 0) { 
					$output[$graph_item["item_id"]]["value_bits"] = process_variables("|" . $match[1] . ":bits:0:" . $match[4] . ":0|", $graph_item, $start_time, $end_time);
					$output[$graph_item["item_id"]]["var_bits"] = "|" . $match[1] . ":bits:0:" . $match[4] . ":0|";
					$output[$graph_item["item_id"]]["type"] = get_pretty_nth_value($match[1]);
				}
				$output[$graph_item["item_id"]]["graph_title"] = $graph_item["title_cache"];
				$output[$graph_item["item_id"]]["value_text"] = process_variables($graph_item["text_format"],$graph_item, $start_time, $end_time);
				$output[$graph_item["item_id"]]["var_text"] = $graph_item["text_format"];
	
			}
			return $output;
		} else {
			return false;
		}
	} else {
		return false;
	}

}

/* process_graph_threshold - Process requested graph and items with concern to threshold 
 *   @arg - $customer_key - Array key of customer to process 
 *   @arg - $graph_id - Graph id to process
 *   @arg - $graph_item_ids - Graph items, empty means all that match
 *   @arg - $start_time - Start time
 *   @arg - $end_time - End time
 *   @arg - $threshold_value - Value that if exceeded causes alert, fuzzy logic for Nth Percentile
 *   @return - array of values
 */
function process_graph_threshold($customer_key, $graph_id, $graph_item_ids, $start_time, $end_time, $current_time, $threshold_value = 0) {
	global $config_track;
	global $config_customers;
	global $config_globals;
	global $rrd_fetch_cache;

	$output = array();
	$interval = 600; /* 10 minute resolution, reduce caching needs by 50% */

	/* current time should not exceed the end time - we will process beyond the billing range */
	if ($current_time > $end_time) {
		$current_time = $end_time;
	}

	/* get graph items id for processing */
	$where = "";
	if (!empty($graph_item_ids)) {
		$where .= " AND i.id IN(" . $graph_item_ids . ")";
	}
	$where .= " AND g.local_graph_id = " . $graph_id;

	$graph_items = db_fetch_assoc("SELECT 
			i.id AS item_id, 
			i.local_graph_id, 
			i.task_item_id, 
			i.text_format, 
			g.id AS graph_id, 
			g.title_cache,
			d.local_data_id,
			d.data_source_name
		FROM 
			graph_templates_graph AS g,
			graph_templates_item AS i LEFT JOIN data_template_rrd as d ON i.task_item_id = d.id 
		WHERE 
			i.local_graph_id = g.local_graph_id AND 
			i.local_graph_id <> 0 AND 
			i.graph_type_id = 1 AND 
			text_format LIKE '%|%:%:%|%'
			" . $where . "
		ORDER BY
			i.local_graph_id;");


	/* Process times based on interval for each graph item */ 
	$customer_description = $config_customers[$customer_key]["description"];
	
	/* Cycle through graph items */
	foreach ($graph_items as $graph_item) {

		/* Record the graph item type we are dealing with */
		$graph_type = "";
		$match = array();
		if (preg_match('/\|sum\:([0-9]+|auto)\:(current|total)\:([0-9]+)\:([0-9]+|auto)\|/',$graph_item["text_format"], $match) > 0) { 
			$graph_type = "Sum";
			$graph_description = "Bandwidth Summation";
		} elseif (preg_match('/\|([0-9]{1,2})\:(\S+)\:([0-9]+)\:(\S+)\:([0-9]+)\|/',$graph_item["text_format"], $match) > 0) { 
			$graph_type = "Nth";
			$graph_description = get_pretty_nth_value($match[1]);
		}

		/* Time loop - Can be slow and uses lots of memory */
		for ($i = $start_time + $interval; $i <= $current_time; $i = $i + $interval) {

			/* check for cached values */
			if (! isset($config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i])) {

				/* fetch data sets from graph for specific graph item id */
				$rrd_fetch_cache = array(); /* No need to cache, we are changing end time which make cache grow exponentially */
				$graph_data = process_graph($graph_id, $graph_item["item_id"], $start_time, $i);

				/* update cache */
				$config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i] = $graph_data[$graph_item["item_id"]]["value_bits"];
			}

			/* Process threshold time stamp search in cache */
			debug("        Processing cache (" . $graph_item["item_id"] . "): " . $i . "        ",0);
			if ($config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i] >= $threshold_value) {
				if ($graph_type == "Sum") {
					$output[$graph_item["item_id"]]["value"] = $config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i];
					$output[$graph_item["item_id"]]["start"] = $start_time;
					$output[$graph_item["item_id"]]["end"] = $i;
					/* No need to continue, we found what we need, let's not waste cpu and memory */
					break;
				}
				if ($graph_type == "Nth") {
					if (! isset($output[$graph_item["item_id"]]["threshold"])) {
						$output[$graph_item["item_id"]]["value"] = $config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i];
						$output[$graph_item["item_id"]]["start"] = $start_time;
						$output[$graph_item["item_id"]]["end"] = $i;
					}
				}	
			}else{
				/* as we progress through time, if we do not exceed the threshold, clear previous stored thresholds */
				if ($graph_type == "Nth") {
					$output[$graph_item["item_id"]]	= array();
				}
			}
		}
		/* Pass the type of graph item we have */
		if (isset($output[$graph_item["item_id"]]["value"])) {
			$output[$graph_item["item_id"]]["type"] = $graph_type;
			$output[$graph_item["item_id"]]["description"] = $graph_description;
		}
		debug("        Processing cache (" . $graph_item["item_id"] . "): done                        ");
		if (isset($config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i])) {
			debug("          Threshold Calculation (" . $graph_type . "): " . $config_track[$customer_description][$graph_id][$graph_item["item_id"]][$start_time][$i] . " >= " . $threshold_value);
		}

	}

	/* Return output array */
	return $output;


}

/* process_variables - Processes Nth Percentile and Bandwidth Summation values for a give graph item.
 *   @arg - $input - String to process variable replacement on
 *   @arg - $graph_item - Variable of graph item to process
 *   @arg - $graph_start - Graph start time
 *   @arg - $graph_end - Graph end time
 *   @return - string replacement
 */
function process_variables($input, $graph_item, $graph_start = 0, $graph_end = 0) {

	$matches = array();
	$match = "";

	/* Get graph items for the graph */
	$graph_items = db_fetch_assoc("SELECT
		graph_templates_item.id AS graph_templates_item_id,
		graph_templates_item.cdef_id,
		graph_templates_item.text_format,
		graph_templates_item.value,
		graph_templates_item.hard_return,
		graph_templates_item.consolidation_function_id,
		graph_templates_item.graph_type_id,
		graph_templates_gprint.gprint_text,
		colors.hex,
		data_template_rrd.id as data_template_rrd_id,
		data_template_rrd.local_data_id,
		data_template_rrd.rrd_minimum,
		data_template_rrd.rrd_maximum,
		data_template_rrd.data_source_name,
		data_template_rrd.local_data_template_rrd_id
		FROM graph_templates_item
		LEFT JOIN data_template_rrd ON (graph_templates_item.task_item_id=data_template_rrd.id)
		LEFT JOIN colors ON (graph_templates_item.color_id=colors.id)
		LEFT JOIN graph_templates_gprint on (graph_templates_item.gprint_id=graph_templates_gprint.id)
		WHERE graph_templates_item.local_graph_id=" . $graph_item["local_graph_id"] . "
		ORDER BY graph_templates_item.sequence");

	/* find the step and how often this graph is updated with new data */
	$ds_step = db_fetch_cell("select
		data_template_data.rrd_step
		from (data_template_data,data_template_rrd,graph_templates_item)
		where graph_templates_item.task_item_id=data_template_rrd.id
		and data_template_rrd.local_data_id=data_template_data.local_data_id
		and graph_templates_item.local_graph_id=" . $graph_item["local_graph_id"] . "
		limit 0,1");
	$ds_step = empty($ds_step) ? 300 : $ds_step;

	if ((empty($graph_start)) || (empty($graph_end))) {
		$rra["rows"] = 600;
		$rra["steps"] = 1;
		$rra["timespan"] = 86400;
	}else{
		/* get a list of RRAs related to this graph */
		$rras = get_associated_rras($graph_item["local_graph_id"]);

		if (sizeof($rras) > 0) {
			foreach ($rras as $unchosen_rra) {
				/* the timespan specified in the RRA "timespan" field may not be accurate */
				$real_timespan = ($ds_step * $unchosen_rra["steps"] * $unchosen_rra["rows"]);

				/* make sure the current start/end times fit within each RRA's timespan */
				if ( (($graph_end - $graph_start) <= $real_timespan) && ((time() - $graph_start) <= $real_timespan) ) {
					/* is this RRA better than the already chosen one? */
					if ((isset($rra)) && ($unchosen_rra["steps"] < $rra["steps"])) {
						$rra = $unchosen_rra;
					}else if (!isset($rra)) {
						$rra = $unchosen_rra;
					}
				}
			}
		}

		if (!isset($rra)) {
			$rra["rows"] = 600;
			$rra["steps"] = 1;
		}
	}
	$seconds_between_graph_updates = ($ds_step * $rra["steps"]);

	/* override: graph start time */
	if ((!isset($graph_start)) || ($graph_start == "0")) {
		$graph_start = -($rra["timespan"]);
	}
	/* override: graph end time */
	if ((!isset($graph_end)) || ($graph_end == "0")) {
		$graph_end = -($seconds_between_graph_updates);
	}

	/* Nth percentile */
	if (preg_match_all("/\|([0-9]{1,2}):(bits|bytes):(\d):(current|total|max|total_peak|all_max_current|all_max_peak|aggregate_max|aggregate_sum|aggregate_current|aggregate):(\d)?\|/", $input, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$input = str_replace($match[0], variable_nth_percentile($match, $graph_item, $graph_items, $graph_start, $graph_end), $input);
		}
	}

	/* bandwidth summation */
	if (preg_match_all("/\|sum:(\d|auto):(current|total|atomic):(\d):(\d+|auto)\|/", $input, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$input = str_replace($match[0], variable_bandwidth_summation($match, $graph_item, $graph_items, $graph_start, $graph_end, $rra["steps"], $ds_step), $input);
		}
	}

	return $input;

}


/*
*****************************************************************************
* Billing Processing Functions 
*****************************************************************************
 */

/* get_start_end_time - get the start and end time for the customer billing
 *   @arg - $customer - Customer data array
 *   @arg - $config_track - Track array
 *   @arg - $config_default - Default Configuration
 *   @arg - $current_time - Current time used to calculate billing
 *   @return - array(start,end)
 */
function get_start_end_time($customer, $config_track, $config_default, $current_time, $date_format = "Y/m/d H:i:s", $start_date_override = "", $threshold_tracking = 0) {

	$start = 0;
	$end = 0;
	$month_mid = 15;

	debug("    Calculating timeframe");

	/* Locate Billing Timeframe - Defined per customer or as a default */
	if (isset($customer["billing_timeframe"])) {
		$billing_timeframe = $customer["billing_timeframe"];
		debug("      Billing timeframe defined on customer");
	}else{
		$billing_timeframe = $config_default["billing_timeframe"];
		debug("      Billing timeframe defined on default");
	}

	/* Check start date - Billing hasn't started for this customer */
	debug("      Checking start date");
	if (empty($start_date_override)) {
		if (isset($billing_timeframe["start_date"])) {
			$start_date = strtotime($billing_timeframe["start_date"]);
			/* if customer hasn't started billing yet, return false */
			debug("      Start Date: " . date($date_format, $start_date));
			if ($start_date > $current_time) {
				return FALSE;
			}
		}else{
			$start_date = 0;
		}
		/* Get previous run for customer */
		debug("      Getting previous start date");
		if (isset($config_track[$customer["description"]])) {
			if (isset($config_track[$customer["description"]]["last_run"])) {
				$start = $config_track[$customer["description"]]["last_run"];
				/* repair time more than 1 year and 1 month in the past */
				if ($start < mktime(0, 0, 0, date("m", $current_time) - 1, date("j", $current_time), date("Y", $current_time) - 1)) {
					$start = mktime(0, 0, 0, date("m", $current_time) - 1, date("j", $current_time), date("Y", $current_time) - 1);	
				}
			}else{
				$start = $start_date;
			}
		}else{
			$start = $start_date;
		}
		debug("        Previous start date: " . date($date_format, $start));
	}else{
		$start_date = $start_date_override;
		$start = $start_date_override;
		debug("      Start date override enabled");
		debug("      Start Date: " . date($date_format, $start_date));
	}

	/* Move current time to the boundry - Previous day end of day */
	$current_time = mktime(0,0,0, date("n", $current_time), date("j", $current_time), date("Y", $current_time)) - 1;
	debug("      Using current time: " . date($date_format, $current_time));

	/* Find last day of the Month */
	$month_last = mktime(23,59,59,date("m",$current_time) + 1, 0, date("Y",$current_time));
	
	/* Figure out if it's time to bill this customer */
	if (isset($billing_timeframe["every"])) {
		if ($billing_timeframe["every"] < 1) {
			$billing_timeframe["every"] = 1;
		}
	}else{
		$billing_timeframe["every"] = 1;
	}
	switch ($billing_timeframe["type"]) {
		
		/* Daily type - boundry day - midnight */
		case "daily";
		debug("      Type: Daily");
		debug("        Every: " . $billing_timeframe["every"]);
		if ($start == 0) {
			$start = $current_time - ($billing_timeframe["every"] * 86400);
		}
		$end = $current_time;
		$threshold_start = $start;
		$threshold_end = $end;
		debug("        Start(" . $start . "): " . date($date_format,$start));
		debug("        End(" . $end . "): " . date($date_format,$end));
		if (($end - $start) < (($billing_timeframe["every"] * 86400) - 1)) {
			debug("    Skipping: Boundary not met");
			$end = 0;
			$start = 0;
		}
		break;

		/* Weekly type - boundry week, sunday - midnight */
		case "weekly";
		debug("      Type: Weekly");
		debug("        Every: " . $billing_timeframe["every"]);
		if ($start == 0) {
			$start = $current_time - ($billing_timeframe["every"] * 604800) + 1;
			/* move start to sunday at midnight */
			$day_of_week = date("w", $start);
			if ($day_of_week != 0) {
				$start = $start - ($day_of_week * 86400); 
			}
			$start = mktime(0,0,0, date("m", $current_time), 1, date("Y", $current_time));

		}
		$end = $current_time;
		$threshold_start = $start;
		$threshold_end = $end;
		debug("        Start(" . $start . "): " . date($date_format,$start));
		debug("        End(" . $end . "): " . date($date_format,$end));
		if (($end - $start) < (($billing_timeframe["every"] * 604800) - 1)) {
			debug("    Skipping: Boundary not met");
			$end = 0;
			$start = 0;
		}
		break;
		
		/* Bi-monthly type - boundry defined day - midnight */
		case "bi-monthly";
		debug("      Type: Bi-Monthly");
	        debug("        Last day of month: " . date($date_format, $month_last));
		if ($start == 0) {
			if (date("j",$current_time) > $month_mid) {
				$start = mktime(0,0,0, date("m", $current_time), 1, date("Y", $current_time));
			}else{
				$start = mktime(0,0,0, date("m", $current_time), $month_mid + 1, date("Y", $current_time));
			}
		}
		$end = $current_time;
		$threshold_start = $start;
		$threshold_end = $end;
		debug("        Start(" . $start . "): " . date($date_format,$start));
		debug("        End(" . $end . "): " . date($date_format,$end));
		if ( (date("j",$current_time) != $month_mid) && (date("j", $current_time) != date("j",$month_last)) ) {
			debug("    Skipping: Boundary not met");
			$start = 0;
			$end = 0;
		}
		break;

		/* Monthly type - boundry defined day - midnight */
		case "monthly";
		debug("      Type: Monthly");
	        debug("        Last day of month: " . date($date_format, $month_last));
		if ($billing_timeframe["every"] > 12) {
			$billing_timeframe["every"] = 12;
		}
		debug("        Every: " . $billing_timeframe["every"]);
		if ($start == 0) {
			$start = mktime(0, 0, 0, date("m",$current_time) - $billing_timeframe["every"], date("j", $current_time) + 1, date("Y",$current_time));
		}
		if (isset($billing_timeframe["day"])) {
			$day = $billing_timeframe["day"];
			if ($billing_timeframe["day"] == "last") {
				$day = $month_last;
			}else{
				if ($day > date("j", $month_last)) {
					$day = $month_last;
				}else{
					$day = mktime(23, 59, 59, date("m", $current_time), $billing_timeframe["day"], date("Y", $current_time));
				}
			}
		}else{
			$day = $month_last;
		}
		$end = mktime(23, 59, 59, date("m", $day), date("j", $day), date("Y", $day));
		$threshold_start = $start;
		$threshold_end = $end;
		debug("        Start(". $start ."): " . date($date_format,$start));
		debug("        End(" . $end . "): " . date($date_format,$end));
		/* Check boundry */
		if (mktime(0, 0, 0, date("m", $current_time), date("j", $current_time), date("Y", $current_time)) == mktime(0, 0, 0, date("m", $day), date("j", $day), date("Y", $day))) {
			/* Check interval */
			if (($end - $start) <= ($end - mktime(0, 0, 0, date("m", $end) - $billing_timeframe["every"] + 1, date("j", $end) + 1, date("Y", $end)))) {
				debug("    Skipping: Interval not met");	
				$end = 0;
				$start = 0;
			}
		}else{
			debug("    Skipping: Boundary not met");	
			$end = 0;
			$start = 0;
		}
		break;
		
	}


	/* Fail safe check of dates */
	if ($start > $end) {
		debug("    Skipping: Start date greater than end date");
		$start = 0;
		$end = 0;
		$threshold_start = $start;
		$threshold_end = $end;
	}

	/* Fix threshold times to process at least 1 day of data */
	if ((($threshold_start == 0) && ($threshold_end == 0)) || ($threshold_start > $threshold_end)) {
		$threshold_end = $current_time;
		$threshold_start = $current_time - 86399;
	}

	/* Create return array */
	if (($end == 0) && ($start == 0) && ($threshold_tracking == 0)) {
		return false;
	}else{
		$times["start"] = $start;
		$times["end"] = $end;
		$times["start_formatted"] = date($date_format, $times["start"]);
		$times["end_formatted"] = date($date_format, $times["end"]);
		if ($threshold_tracking == 1) {
			$times["threshold_start"] = $threshold_start;
			$times["threshold_end"] = $threshold_end;
		}
		return $times;
	}

}

/* calculate_rate - Given rate array and bits, returns calculated value */
function calculate_rate($rate, $type, $bits) {

	$output = 0;

	if ($type == "overage") {
		if ($bits > $rate["rate_overage"]["threshold"]) {
			$bits = $bits - $rate["rate_overage"]["threshold"];
		}else{
			$bits = 0;
		}	
		$multi = unit_multi($rate["rate_overage"]["unit"]);
		$output = ($bits / $multi) * $rate["rate_overage"]["amount"];
	} elseif ($type == "committed") {
		if ($bits > $rate["rate_overage"]["threshold"]) {
			$bits = $rate["rate_overage"]["threshold"];
		}	
		$multi = unit_multi($rate["rate_committed"]["unit"]);
		$output = ($bits / $multi) * $rate["rate_committed"]["amount"];
		if (isset($rate["rate_committed"]["minimum"])) {
			if ($output < $rate["rate_committed"]["minimum"]) {
				$output = $rate["rate_committed"]["minimum"];
			}
		}
	} elseif ($type == "regular") {
		$multi = unit_multi($rate["rate"]["unit"]);
		$output = ($bits / $multi) * $rate["rate"]["amount"];
		if (isset($rate["rate"]["minimum"])) {
			if ($output < $rate["rate"]["minimum"]) {
				$output = $rate["rate"]["minimum"];
			}
		}
	} elseif ($type == "fixed") {
		$output = $rate["rate_fixed"]["amount"];
	}
	
	return round($output, 4);

}


/* 
*****************************************************************************
* Configuration Functions
*****************************************************************************
 */

/* read_config - Read and verify configuration file  
 *   @arg - $config_file - Configuration file
 *   @return - boolean - True = good, False = bad
 */
function read_config($config_file) {
	global $config_globals, $config_defaults, $config_customers;

	$issues = "";
	$dup_check = array();

	if (empty($config_file)) {
		print "Invalid configuration file\n";
		return false;
	}

	if (! $config_FH = @fopen($config_file, "r")) {
		print "ERROR: Unable to open configuration file.\n";
		return;
	}
	$data = fread($config_FH, filesize($config_file));
	if (empty($data)) {
		print "ERROR: Configuration file seems to be empty.\n";
		return;
	}
	fclose($config_FH);
	$objXML = new xml2Array();
	if (!$config = $objXML->parse($data)) {
		print "ERROR: Invalid Configuration file (XML Parse Error)\n";
	 	exit;
	}

	/* Check the basics */
	if (!isset($config[0]["name"])) {
		print "Invalid formatted XML configuration file, file must start with <cacti_biller>\n";
		return false;
	}
	if ($config[0]["name"] != "cacti_biller") {
		print "Invalid formatted XML configuration file, file must start with <cacti_biller>\n";
		return false;
	}
	if (!isset($config[0]["children"])) {
		print "Invalid formatted XML configuration file, no sub items defined\n";
		return false;
	}

	/* Initialize variable */
	$config_defaults = array();
	$config_customers = array();
	$check_email = FALSE;
	$check_default = FALSE;
	$default_issues = "";
	$email_issues = "";

	/* Set default currency */
	$config_defaults["currency"]["pre"] = "\$";
	$config_defaults["currency"]["post"] = "";
	$config_defaults["currency"]["precision"] = 2;

	/* Set Threshold tracking default */
	$config_globals["threshold_tracking"]["enabled"] = 0;
	$config_globals["threshold_tracking"]["notification"] = 0;
	$config_globals["threshold_tracking"]["subject"] = "Cacti ISP Billing - Bandwidth Overage Notification";
	$config_globals["threshold_tracking"]["title"] = "Cacti ISP Billing Notification";

	/* Set email defaults */
	$config_globals["email"]["title"] = "Cacti ISP Billing";
	$config_globals["email"]["subject"] = "Cacti ISP Billing";
	$config_globals["email"]["csv_file_name"] = "cacti_billing";
	if (isset($_SERVER["HOSTNAME"])) {
		$config_globals["email"]["return_address"] = "cacti@" . $_SERVER["HOSTNAME"];
	}else{
		$config_globals["email"]["return_address"] = "cacti@localhost";
	}

	/* Build global, customer and default confi arrays */
	foreach ($config[0]["children"] as $child_root) {

		/* process global sections */
		if ($child_root["name"] == "global") {
			foreach ($child_root["children"] as $child_global) {

				/* global email */
				if ($child_global["name"] == "email") {
					if (isset($child_global["children"])) {
						foreach ($child_global["children"] as $child_email) {
							if ($child_email["name"] == "return_address") {
								if (! empty($child_email["tagData"])) {
									$config_globals["email"]["return_address"] = $child_email["tagData"];
								}
							}
							if ($child_email["name"] == "subject") {
								if (! empty($child_email["tagData"])) {
									$config_globals["email"]["subject"] = $child_email["tagData"];
								}
							}
							if ($child_email["name"] == "title") {
								if (! empty($child_email["tagData"])) {
									$config_globals["email"]["title"] = $child_email["tagData"];
								}
							}
							if ($child_email["name"] == "csv_file_name") {
								if (! empty($child_email["tagData"])) {
									$config_globals["email"]["csv_file_name"] = $child_email["tagData"];
								}
							}
							if ($child_email["name"] == "footer") {
								if (! empty($child_email["tagData"])) {
									$config_globals["email"]["footer"] = $child_email["tagData"];
								}
							}

						}
					}
				}

				/* Post export file processor */
				if ($child_global["name"] == "export_file_processor") {
					$config_globals["export_file_processor"] = $child_global["tagData"];
				}

				/* Threshold tracking */
				if ($child_global["name"] == "threshold") {
					if (isset($child_global["children"])) {
						foreach ($child_global["children"] as $child_threshold) {
							if ($child_threshold["name"] == "enabled") {
								if ($child_threshold["tagData"] == "yes") {
									$config_globals["threshold_tracking"]["enabled"] = 1;
								}
							}
							if ($child_threshold["name"] == "notification") {
								if ($child_threshold["tagData"] == "yes") {
									$config_globals["threshold_tracking"]["notification"] = 1;
								}
							}
							if ($child_threshold["name"] == "subject") {
								$config_globals["threshold_tracking"]["subject"] = $child_threshold["tagData"];
							}
							if ($child_threshold["name"] == "title") {
								$config_globals["threshold_tracking"]["title"] = $child_threshold["tagData"];
							}
						}
					}
				}

				/* global defaults */
				if ($child_global["name"] == "defaults") {
					if (! isset($child_global["children"])) {
						print "Invalid '<defaults> section defined in '<global>'\n";
						return false;
					}
					$e = 0;
					$check_default = TRUE;
					foreach ($child_global["children"] as $child_default) {
						if ($child_default["name"] == "email") {
							if (isset($child_default["tagData"])) {
								$config_defaults["email"][$e]["address"] = $child_default["tagData"];
								$config_defaults["email"][$e]["html"] = 1;
								$config_defaults["email"][$e]["csv"] = 1;
								$config_defaults["email"][$e]["type"] = "all";
								$check_email = TRUE;
								if (sizeof($child_default["attrs"]) > 0) {
									if (isset($child_default["attrs"]["html"])) {
										if ( $child_default["attrs"]["html"] == "no") {
											$config_defaults["email"][$e]["html"] = 0;
										}
									}
									if (isset($child_default["attrs"]["csv"])) {
										if ($child_default["attrs"]["csv"] == "no") {
											$config_defaults["email"][$e]["csv"] = 0;
										}
									}
									if (isset($child_default["attrs"]["type"])) {
										if (($child_default["attrs"]["type"] == "all") or ($child_default["attrs"]["type"] == "billing") or ($child_default["attrs"]["type"] == "threshold")) {
											$config_defaults["email"][$e]["type"] = $child_default["attrs"]["type"];
										}else{
											$default_issues .= "    Invalid '<email>' 'type' attribute set for email '" . $config_defaults["email"][$e]["address"]  . "' in section '<defaults>'\n";
										}
									}
								}
								$e++;
							}else{
								$default_issues .= "Invalid '<email>' section defined in '<defaults>'\n";
							}
						}
						if ($child_default["name"] == "file") {
							if (isset($child_default["tagData"])) {
								$config_defaults["file"]["path"] = $child_default["tagData"];
								$config_defaults["file"]["format"] = "csv";
								$config_defaults["file"]["process"] = 1;
								if (isset($child_default["attrs"]["process"])) {
									if ($child_default["attrs"]["process"] == "no") {
										$config_defaults["file"]["process"] = 0;
									}
								}
							}else{
								$default_issues .= "Invalid '<file>' section defined in '<defaults>'\n";
							}
						}

						if ($child_default["name"] == "rate") {
							if (sizeof($child_default["attrs"]) > 0) {
								$rate_name = "rate";
								if (isset($child_default["attrs"]["type"])) {
									if ($child_default["attrs"]["type"] == "committed") {
										$rate_name .= "_committed";
									} elseif ($child_default["attrs"]["type"] == "overage") {
										$rate_name .= "_overage";
									} elseif ($child_default["attrs"]["type"] == "fixed") {
										$rate_name .= "_fixed";
									}
								}
								if (isset($child_default["attrs"]["unit"])) {
									$config_defaults[$rate_name]["unit"] = $child_default["attrs"]["unit"];
								} else {
									$config_defaults[$rate_name]["unit"] = "Mb";
								}
								if (isset($child_default["attrs"]["threshold"])) {
									if ($rate_name == "rate_overage") {
										 $config_defaults[$rate_name]["threshold"] = $child_default["attrs"]["threshold"];
									}
								}
								if (isset($child_default["attrs"]["minimum"])) {
									if (($rate_name == "rate_committed") || ($rate_name == "rate")) {
										 $config_defaults[$rate_name]["minimum"] = $child_default["attrs"]["minimum"];
									}
								}
								if (isset($child_default["tagData"])) {
									$config_defaults[$rate_name]["amount"] = $child_default["tagData"];
								}
							} else {
								if (isset($child_default["tagData"])) {
									$config_defaults["rate"]["amount"] = $child_default["tagData"];
								}
								$config_defaults["rate"]["unit"] = "Mb";
							}
						}
						if ($child_default["name"] == "billing_timeframe") {
							foreach ($child_default["children"] as $child_timeframe) {
								$config_defaults["billing_timeframe"][$child_timeframe["name"]] = $child_timeframe["tagData"];
							}
						}
						if ($child_default["name"] == "currency") {
							if (isset($child_default["children"])) {
								foreach ($child_default["children"] as $child_currency) {
									if ($child_currency["name"] == "pre") {
										$config_defaults["currency"]["pre"] = $child_currency["tagData"];
									}
									if ($child_currency["name"] == "post") {
										$config_defaults["currency"]["post"] = $child_currency["tagData"];
									}
									if ($child_currency["name"] == "precision") {
										$config_defaults["currency"]["precision"] = $child_currency["tagData"];
									}
								}
							}
						}

					}
					if (! isset($config_defaults["billing_timeframe"])) {
						$default_issues .= "  No 'billing_timeframe' defined in global section.\n";	
					}else{
						$default_issues .= check_billing_timeframe($config_defaults["billing_timeframe"], "global");
					}
					if (! $check_email) {
						$default_issues .= "  No 'email' defined in global section.\n";	
					}
					$default_issues .= check_rate($config_defaults, "default", true);
					if (strlen($default_issues) > 0) {
						$issues .= "  Defaults section has the following issues:\n" . $default_issues;
					}
				}
			}
			if (! $check_default) {
				$issues .= "  No Defaults section defined in Globals.\n";
			}

		/* process customers section */
		} elseif ($child_root["name"] == "customers") {
			$i = 0;
			if (isset($child_root["children"])) {
				foreach ($child_root["children"] as $child_customer) {
					$j=0;
					$e=0;
					if (!isset($child_customer["children"])) {
						print "Customer section defined with no child items\n";
						return false;
					}
					$customer_issues = "";
					foreach ($child_customer["children"] as $child_customer_data) {
						if ($child_customer_data["name"] == "description") {
							if (isset($child_customer_data["tagData"])) {
								$config_customers[$i]["description"] = $child_customer_data["tagData"];
								if (isset($dup_check[$child_customer_data["tagData"]])) {
									$issues .= "Duplicate customer description detected: \'" . $child_customer_data["tagData"] . "\'\n";
								}else{
									$dup_check[$child_customer_data["tagData"]] = "1";
								}
							}else{
								$issues .= "Customer section description has a empty value.\n";
							}
						}
						if ($child_customer_data["name"] == "external_id") {
							if (isset($child_customer_data["tagData"])) {
								$config_customers[$i]["external_id"] = $child_customer_data["tagData"];
							}
						}
						if ($child_customer_data["name"] == "email") {
							if (isset($child_customer_data["tagData"])) {
								$config_customers[$i]["email"][$e]["address"] = $child_customer_data["tagData"];
								$config_customers[$i]["email"][$e]["html"] = 1;
								$config_customers[$i]["email"][$e]["csv"] = 1;
								$config_customers[$i]["email"][$e]["type"] = "all";
								if (sizeof($child_customer_data["attrs"]) > 0) {
									if (isset($child_customer_data["attrs"]["html"])) {
										if ($child_customer_data["attrs"]["html"] == "no") {
											$config_customers[$i]["email"][$e]["html"] = 0;
										}
									}
									if (isset($child_customer_data["attrs"]["csv"])) {
										if ($child_customer_data["attrs"]["csv"] == "no") {
											$config_customers[$i]["email"][$e]["csv"] = 0;
										}
									}
									if (isset($child_customer_data["attrs"]["type"])) {
										if (($child_customer_data["attrs"]["type"] == "all") or ($child_customer_data["attrs"]["type"] == "billing") or ($child_customer_data["attrs"]["type"] == "threshold")) {
											$config_customers[$i]["email"][$e]["type"] = $child_customer_data["attrs"]["type"];
										}else{
											$customer_issues .= "    Invalid '<email>' 'type' attribute set for email '" . $config_customers[$i]["email"][$e]["address"]  . "'\n";
										}
									}
								}
							}
							$e++;
						}
						if ($child_customer_data["name"] == "file") {
							if (isset($child_customer_data["tagData"])) {
								$config_customers[$i]["file"]["path"] = $child_customer_data["tagData"];
								$config_customers[$i]["file"]["format"] = "csv";
								$config_customers[$i]["file"]["process"] = 1;
								if (isset($child_customer_data["attrs"]["process"])) {
									if ($child_customer_data["attrs"]["process"] == "no") {
										$config_customers[$i]["file"]["process"] = 0;
									}
								}
							}
						}
						if ($child_customer_data["name"] == "rate") {
							if (sizeof($child_customer_data["attrs"]) > 0) {
								$rate_name = "rate";
								if (isset($child_customer_data["attrs"]["type"])) {
									if ($child_customer_data["attrs"]["type"] == "committed") {
										$rate_name .= "_committed";
									} elseif ($child_customer_data["attrs"]["type"] == "overage") {
										$rate_name .= "_overage";
									} elseif ($child_customer_data["attrs"]["type"] == "fixed") {
										$rate_name .= "_fixed";
									}
								}
								if (isset($child_customer_data["attrs"]["unit"])) {
									$config_customers[$i][$rate_name]["unit"] = $child_customer_data["attrs"]["unit"];
								} else {
									$config_customers[$i][$rate_name]["unit"] = "Mb";
								}
								if (isset($child_customer_data["attrs"]["threshold"])) {
									if ($rate_name == "rate_overage") {
										 $config_customers[$i][$rate_name]["threshold"] = $child_customer_data["attrs"]["threshold"];
									}
								}
								if (isset($child_customer_data["attrs"]["minimum"])) {
									if (($rate_name == "rate_committed") || ($rate_name == "rate")) {
										 $config_customers[$i][$rate_name]["minimum"] = $child_customer_data["attrs"]["minimum"];
									}
								}
								if (isset($child_customer_data["tagData"])) {
									$config_customers[$i][$rate_name]["amount"] = $child_customer_data["tagData"];
								}
							} else {
								if (isset($child_customer_data["tagData"])) {
									$config_customers[$i]["rate"]["amount"] = $child_customer_data["tagData"];
								}
								$config_customers[$i]["rate"]["unit"] = "Mb";
							}
						}
						if ($child_customer_data["name"] == "currency") {
							if (isset($child_customer_data["children"])) {
								foreach ($child_customer_data["children"] as $child_currency) {
									if ($child_currency["name"] == "pre") {
										$config_customers[$i]["currency"]["pre"] = $child_currency["tagData"];
									}
									if ($child_currency["name"] == "post") {
										$config_customers[$i]["currency"]["post"] = $child_currency["tagData"];
									}
									if ($child_currency["name"] == "precision") {
										$config_customers[$i]["currency"]["precision"] = $child_currency["tagData"];
									}
								}
							}
						}
						if ($child_customer_data["name"] == "graphs") {
							if (isset($child_customer_data["children"])) {
								foreach ($child_customer_data["children"] as $child_customer_data_graphs) {
									if ($child_customer_data_graphs["name"] == "graph") {
										/* elements in the graph */
										if (isset($child_customer_data_graphs["children"])) {
											foreach ($child_customer_data_graphs["children"] as $child_customer_data_graph) {
												if ($child_customer_data_graph["name"] == "id") {
													if (isset($child_customer_data_graph["tagData"])) {
														$config_customers[$i]["graphs"][$j]["id"] = $child_customer_data_graph["tagData"];
													}else{
														$config_customers[$i]["graphs"][$j]["id"] = "";
													}
												}
												if ($child_customer_data_graph["name"] == "external_id") {
													if (isset($child_customer_data_graph["tagData"])) {
														$config_customers[$i]["graphs"][$j]["external_id"] = $child_customer_data_graph["tagData"];
													}
												}
												if ($child_customer_data_graph["name"] == "rate") {
													if (sizeof($child_customer_data_graph["attrs"]) > 0) {
														$rate_name = "rate";
														if (isset($child_customer_data_graph["attrs"]["type"])) {
															if ($child_customer_data_graph["attrs"]["type"] == "committed") {
																$rate_name .= "_committed";
															} elseif ($child_customer_data_graph["attrs"]["type"] == "overage") {
																$rate_name .= "_overage";
															} elseif ($child_customer_data_graph["attrs"]["type"] == "fixed") {
																$rate_name .= "_fixed";
															}
														}
														if (isset($child_customer_data_graph["attrs"]["unit"])) {
															$config_customers[$i]["graphs"][$j][$rate_name]["unit"] = $child_customer_data_graph["attrs"]["unit"];
														} else {
															$config_customers[$i]["graphs"][$j][$rate_name]["unit"] = "Mb";
														}
														if (isset($child_customer_data_graph["attrs"]["threshold"])) {
															if ($rate_name == "rate_overage") {
																 $config_customers[$i]["graphs"][$j][$rate_name]["threshold"] = $child_customer_data_graph["attrs"]["threshold"];
															}
														}
														if (isset($child_customer_data_graph["attrs"]["minimum"])) {
															if (($rate_name == "rate_committed") || ($rate_name == "rate")) {
																 $config_customers[$i]["graphs"][$j][$rate_name]["minimum"] = $child_customer_data_graph["attrs"]["minimum"];
															}
														}
														if (isset($child_customer_data_graph["tagData"])) {
															$config_customers[$i]["graphs"][$j][$rate_name]["amount"] = $child_customer_data_graph["tagData"];
														}
													} else {
														if (isset($child_customer_data_graph["tagData"])) {
															$config_customers[$i]["graphs"][$j]["rate"]["amount"] = $child_customer_data_graph["tagData"];
														}
														$config_customers[$i]["graphs"][$j]["rate"]["unit"] = "Mb";
													}
												}
												if ($child_customer_data_graph["name"] == "graph_item_id") {
													if (!isset($config_customers[$i]["graphs"][$j]["graph_item_id"])) {
														$config_customers[$i]["graphs"][$j]["graph_item_id"] = array();
													}
													array_push($config_customers[$i]["graphs"][$j]["graph_item_id"], $child_customer_data_graph["tagData"]);
												}
											}
										}
										$j++;
									}
								}
							}
						}
						if ($child_customer_data["name"] == "billing_timeframe") {
							foreach ($child_customer_data["children"] as $child_timeframe) {
								$config_customers[$i]["billing_timeframe"][$child_timeframe["name"]] = $child_timeframe["tagData"];
							}
						}
					}
					/* Customer field checks */
					if (! isset($config_customers[$i]["description"])) {
						$issues .= "  Customer section missing required 'description'.";
					}else{
						$customer_issues .= check_rate($config_customers[$i], "customer");
						$graph_issues = "";
						if (isset($config_customers[$i]["graphs"])) {
							foreach ($config_customers[$i]["graphs"] as $graph) {
								if (empty($graph["id"])) {
									$customer_issues .= "    Empty graph id detected.\n";
								} else {
									$graph_issues = check_rate($graph, "graph");
									if (strlen($graph_issues) > 0) {
										$customer_issues .= "    Graph id: " . $graph["id"] . "\n" . $graph_issues;
									}
								}
							}
						}else{
							$customer_issues .= "    No graphs define.\n";
						}
						if (isset($config_customers[$i]["billing_timeframe"])) {
							$customer_issues .= check_billing_timeframe($config_customers[$i]["billing_timeframe"]);
						}
						if (strlen($customer_issues) > 0) {
							$issues .= "  Customer section '" . $config_customers[$i]["description"] . "' has the following issues:\n" . $customer_issues;
						}
					}
					$i++;
				}
			}
		} else {
			print "Unknown section '" . $child_root["name"] . "' encountered\n";
			return false;
		}

	}

	if (sizeof($config_customers) < 1) {
		$issues .= "  No customers are defined.\n";
	}

	if (strlen($issues) > 0) {
		print "The following configuration issues where found:\n";
		print $issues . "\n";
		return false;
	}else{
		return true;
	}


}

/* check_rate - check a give rate for proper format */
function check_rate ($rate, $section = "", $required = false) {

	$issues = "";
	$space = "    ";
	$multiple_check = array();

	if ($section == "graph") {
		$space = "      ";
	}

	/* Regular rate check */
	if (isset($rate["rate"])) {
		if (isset($rate["rate"]["amount"])) {
			if (! is_numeric($rate["rate"]["amount"])) {
				$issues .= $space . "Rate amount is not numeric.\n";
			}
		}else{
			$issues .= $space . "Rate has no defined amount.\n";
		}
		if (! ( ($rate["rate"]["unit"] == "Kb") || ($rate["rate"]["unit"] == "Mb") || ($rate["rate"]["unit"] == "Gb") ) ) {
			$issues .= $space . "Invalid unit on rate, allowed: Kb, Mb or Gb.\n";
		}
		if (isset($rate["rate"]["minimum"])) {
			if (! is_numeric($rate["rate"]["minimum"])) {
				$issues .= $space . "Minimum defined for rate is not numeric.\n";
			}else{
				if ($rate["rate"]["minimum"] < 0) {
					$issues .= $space . "Minimum defined for rate is less than zero.\n";
				}
			}
		}
		$multiple_check[] = "Regular";

	}

	/* Overage/Committed rate check */
	if (isset($rate["rate_overage"])) {
		if (isset($rate["rate_committed"])) {
			if (isset($rate["rate_committed"]["amount"])) {
				if (! is_numeric($rate["rate_committed"]["amount"])) {
					$issues .= $space . "Committed rate amount is not numeric.\n";
				}
			}else{
				$issues .= $space . "Committed rate has no defined amount.\n";
			}
			if (isset($rate["rate_overage"]["amount"])) {
  				if (! is_numeric($rate["rate_overage"]["amount"])) {
					$issues .= $space . "Overage rate amount is not numeric.\n";
				}
			}else{
				$issues .= $space . "Overage rate has no defined amount.\n";
			}
			if (! ( ($rate["rate_committed"]["unit"] == "Kb") || ($rate["rate_committed"]["unit"] == "Mb") || ($rate["rate_committed"]["unit"] == "Gb") ) ) {
				$issues .= $space . "Invalid unit on committed rate, allowed: Kb, Mb or Gb.\n";
			}
			if (! ( ($rate["rate_overage"]["unit"] == "Kb") || ($rate["rate_overage"]["unit"] == "Mb") || ($rate["rate_overage"]["unit"] == "Gb") ) ) {
				$issues .= $space . "Invalid unit on overage rate, allowed: Kb, Mb or Gb.\n";
			}
			if (isset($rate["rate_overage"]["threshold"])) {
				if (! is_numeric($rate["rate_overage"]["threshold"])) {
					$issues .= $space . "Threshold defined for overage rate is not numeric.\n";
				}
			}else{
				$issues .= $space . "No threshold defined for overage rate.\n";
			}
			if (isset($rate["rate_committed"]["minimum"])) {
				if (! is_numeric($rate["rate_committed"]["minimum"])) {
					$issues .= $space . "Minimum defined for committed rate is not numeric.\n";
				}else{
					if ($rate["rate_committed"]["minimum"] < 0) {
						$issues .= $space . "Minimum defined for committed rate is less than zero.\n";
					}
				}
			}
			$multiple_check[] = "Overage/Committed";
		}else{
			$issues .= $space . "No committed rate defined where overage rate defined.\n";
		}
	}

	/* Fixed rate check */
	if (isset($rate["rate_fixed"])) {
		if (isset($rate["rate_fixed"]["amount"])) {
			if (! is_numeric($rate["rate_fixed"]["amount"])) {
				$issues .= $space . "Fixed rate amount is not numeric.\n";
			}
		}else{
			$issues .= $space . "Fixed rate has no defined amount.\n";
		}
		$multiple_check[] = "Fixed";
	}

	/* Check for Committed without Overage rate */
	if (! isset($rate["rate_overage"])) {	
		if (isset($rate["rate_committed"])) {
			$issues .= $space . "Found committed rate, but no overage rate.  Use rate instead for no overage billing.\n";
		}
	}	

	/* Check for multiple defined rates or no defined rates */
	if (sizeof($multiple_check) < 1) {
		if ($required) {
			$issues .= $space . "No rates found in " . $section . " section.\n";
		}
	} elseif (sizeof($multiple_check) > 1) {
		$issues .= $space . "Multiple rates (" . implode(",", $multiple_check) . ") defined in " . $section . " section.\n";
	}

	$multiple_check = array();

	return $issues;

}

/* check_billing_timeframe - Check a given billing timeframe for proper format */
function check_billing_timeframe ($billing_timeframe, $section = "") {

	$issues = "";

	if (isset($billing_timeframe["type"])) {
		if (! preg_match("/^(daily|weekly|monthly|bi-monthly)$/", $billing_timeframe["type"])) {
			$issues .= "    Invalid 'billing_timeframe' 'type' defined, allowed values are: daily, weekly, monthly and bi-monthly.\n";
		}
		if ($billing_timeframe["type"] == "monthly") {
			if (isset($billing_timeframe["day"])) {
				if (is_numeric($billing_timeframe["day"])) {
					if (($billing_timeframe["day"] < 1) || ($billing_timeframe["day"] > 31)) {
						$issues .= "    Value 'billing_timeframe' 'day' must be a value of 1 to 31 or 'last'.\n";
					}	
				}else{
					if ($billing_timeframe["day"] != "last") {
						$issues .= "    Value 'billing_timeframe' 'day' must be a value of 1 to 31 or 'last'.\n";
					}
				}
			}
		}
		if (isset($billing_timeframe["every"])) {
			if (! is_numeric($billing_timeframe["every"])) {
				$issues .= "    Value 'billing_timeframe' 'every' must be numeric.\n";
			}
		}
	}else{
		$issues .= "    No 'billing_timeframe' 'type' defined.\n";
	}
	if (isset($billing_timeframe["start_date"])) {
		if (strtotime($billing_timeframe["start_date"]) == FALSE) {
			$issues .= "    Invalid 'billing_timeframe' 'start_date' defined, date format issue.\n";
		}
	}

	if (strlen($issues) > 0) { 
		if (strlen($section) > 0) {
			$issues = "  Billing timeframe issues found in " . $section . " section\n" . $issues;
		}
	}
	return $issues;

}

/* build_config - Build XML example configuration from billable graphs 
 *   @arg - $file - File to write configuration to
 *   @arg - $filter - Filter certain graph ids, comma seperated string of numbers
 *   @arg - $email - Set email address
 *   @arg - $html - Set email format to html
 *   @arg - $csv - Set email to include csv file 
 */
function build_config($file = "", $filter = "", $email = "", $html = 1, $csv = 1) {
	global $config;

	if (empty($file)) {
		$file = "example.xml";
	}

	/* get start date of the month */
	$start_date = date("Y/m/d", mktime(0,0,0, date("n", time()), 1, date("Y", time())));

	/* define email if needed */
	if (empty($email)) {
		$email = "root@localhost";
	}

	/* define XML encoding */
	$XMLEncoding = "UTF-8";
	if ($config["cacti_server_os"] == "WIN") {
		$XMLEncoding = "ISO-8859-1";
	}

	/* open file */
	if (!$xml_FH = fopen($file, "w")) {
		print "ERROR: Unable to open file for writing: $file\n";
		exit;
	}

	$where = "";
	if (! empty($filter)) {
		$where = " i.local_graph_id in(" . $filter . ") AND ";
	}

	/* Query database for items of interest */
	$graph_items = db_fetch_assoc("SELECT 
			i.id AS item_id, 
			i.local_graph_id, 
			i.task_item_id, 
			i.text_format, 
			g.id AS graph_id, 
			g.title_cache
		FROM 
			graph_templates_item AS i, 
			graph_templates_graph AS g
		WHERE 
			i.local_graph_id = g.local_graph_id AND 
			i.local_graph_id <> 0 AND 
			i.graph_type_id = 1 AND " . $where . " 
			text_format LIKE '%|%:%:%|%'
		ORDER BY
			i.local_graph_id;");

	/* write header information */
	fwrite($xml_FH, "<?xml version=\"1.0\" encoding=\"" . $XMLEncoding . "\" standalone=\"yes\"?");
	fwrite($xml_FH, ">\n");
	fwrite($xml_FH, "<cacti_biller>\n");
	fwrite($xml_FH, "  <global>\n");
	fwrite($xml_FH, "    <!-- Global Threshold tracking for reporting and notification of date/time of overage occurance -->\n");
	fwrite($xml_FH, "    <threshold>\n");
	fwrite($xml_FH, "      <enabled>no</enabled>\n");
	fwrite($xml_FH, "      <notification>no</notification>\n");
	fwrite($xml_FH, "    </threshold>\n");
	fwrite($xml_FH, "    <!-- Defaults are used when the same elements are not defined for a customer -->\n");
	fwrite($xml_FH, "    <defaults>\n");
	fwrite($xml_FH, "      <email html=\"");
	if ($html == 1) {
		fwrite($xml_FH, "yes");
	}else{
		fwrite($xml_FH, "no");
	}	
	fwrite($xml_FH, "\" csv=\"");
	if ($csv == 1) {
		fwrite($xml_FH, "yes");
	}else{
		fwrite($xml_FH, "no");
	}	
	fwrite($xml_FH, "\">" . $email . "</email>\n");
	fwrite($xml_FH, "      <rate unit=\"Mb\">0.00</rate><!-- Rate per Kb, Mb or Gb used for calculating billing amounts -->\n");
	fwrite($xml_FH, "      <billing_timeframe>\n");
	fwrite($xml_FH, "        <type>monthly</type><!-- Values: bi-monthly,monthly,weekly,daily -->\n");
	fwrite($xml_FH, "        <day>last</day><!-- Values: integer, dependant on type selected, last keyword selects last day of week(Saturday), month or year -->\n");
	fwrite($xml_FH, "        <every>1</every><!-- Values: integer, dependant on type selected, example: type=monthly, day=last, every=1\n                        means billing will be processed every month on the last day.  If every=2, then it would\n                        be every 2 months on the last day.   -->\n");
	fwrite($xml_FH, "        <start_date>" . $start_date . "</start_date><!-- Date to start billing on, format: YYYY-MM-DD -->\n");
	fwrite($xml_FH, "      </billing_timeframe>\n");
	fwrite($xml_FH, "    </defaults>\n");
	fwrite($xml_FH, "  </global>\n\n");

	/* create customers */
	fwrite($xml_FH, "  <!-- This is the customer section.  All graphs are considered seperate customers, if you care to \n  add multiple graphs to a single customer follow the examples-->\n");
	fwrite($xml_FH, "  <customers>\n");
	fwrite($xml_FH, "<!-- Example customer syntax -->\n");
	fwrite($xml_FH, "<!--    <customer> -->\n");
	fwrite($xml_FH, "<!--      <description>Customer Description</description>--><!-- Customer Description, this will show on billing reports -->\n");
	fwrite($xml_FH, "<!--      <rate unit=\"Mb\">0.01</rate>--><!-- Rate per Kb, Mb or Gb used for calculating billing amounts per customer -->\n");
	fwrite($xml_FH, "<!--      <graphs> -->\n");
	fwrite($xml_FH, "<!--        <graph> --><!-- Example of single graph item selection from a graph-->\n");
	fwrite($xml_FH, "<!--          <id>1</id>--><!-- Graph Title: \"Customer Graph 1\" -->\n");
	fwrite($xml_FH, "<!--          <rate unit=\"Mb\" type=\"committed\">0.01</rate>--><!-- Rate per Kb, Mb or Gb used for calculating billing amounts per customer\n                              at committed rate -->\n");
	fwrite($xml_FH, "<!--          <rate unit=\"Mb\" type=\"overage\" threshold=\"23947239\">0.02</rate>--><!-- Rate per Kb, Mb or Gb used for\n                              calculating billing amounts per customer at overage rate, threshold parameter\n                              determines what is considered overage.  When overage is detected, overage rate is\n                              calculated for amount above overage threshold-->\n");
	fwrite($xml_FH, "<!--          <graph_item_id>3</graph_item_id>--><!-- Comment: \"95th Percentile: |95:bits:6:max:2| mbits\" -->\n");
	fwrite($xml_FH, "<!--        </graph> -->\n");
	fwrite($xml_FH, "<!--        <graph> --><!-- Example of all graph item selection from a graph-->\n");
	fwrite($xml_FH, "<!--          <id>2</id>--><!-- Graph Title: \"Customer Graph 2\" -->\n");
	fwrite($xml_FH, "<!--        </graph> -->\n");
	fwrite($xml_FH, "<!--      </graphs> -->\n");
	fwrite($xml_FH, "<!--      <email>root@localhost</email>-->\n");
	fwrite($xml_FH, "<!--      <billing_timeframe>-->\n");
	fwrite($xml_FH, "<!--        <type>monthly</type>--><!-- Values: bi-monthly,monthly,weekly,daily -->\n");
	fwrite($xml_FH, "<!--        <day>last</day>--><!-- Values: integer, dependant on type selected, last keyword selects last day of week, month or year -->\n");
	fwrite($xml_FH, "<!--        <every>1</every>--><!-- Values: integer, dependant on type selected, example: type=monthly, day=last, every=1\n                        means billing will be processed every month on the last day.  If every=2, then it would\n                        be every 2 months on the last day.   -->\n");
	fwrite($xml_FH, "<!--        <start_date>2006-07-01</start_date>--><!-- Date to start billing on, format: YYYY-MM-DD -->\n");
	fwrite($xml_FH, "<!--      </billing_timeframe>-->\n");
	fwrite($xml_FH, "<!--    </customer>-->\n\n");


	$local_graph_id_prev = 0;
	$item_type = "N/A";
	$match = array();
	if (sizeof($graph_items) > 0) {

		fwrite($xml_FH, "    <customer>\n");
		foreach ($graph_items as $graph_item) {
			

			$item_type = "N/A";
			$match = array();
			if (preg_match('/\|sum\:.*\|/',$graph_item["text_format"]) > 0) { 
				$item_type = "Bandwidth Summation";
			} elseif (preg_match('/\|([0-9]{1,2})\:[\:0-9a-z]+\|/',$graph_item["text_format"], $match) > 0) { 
				$item_type = get_pretty_nth_value($match[1]);
			}

			if ($graph_item["local_graph_id"] <> $local_graph_id_prev) {	
				if ($local_graph_id_prev <> 0) {
					fwrite($xml_FH, "        </graph>\n      </graphs>\n    </customer>\n\n    <customer>\n");
				}
				fwrite($xml_FH, "      <description>" . xml_character_encode($graph_item["title_cache"] . " - " . $graph_item["local_graph_id"]) . "</description><!-- Customer Description, this will show on billing reports -->\n");
				fwrite($xml_FH, "      <graphs>\n");
				fwrite($xml_FH, "        <graph>\n");
				fwrite($xml_FH, "          <id>" . $graph_item["local_graph_id"] . "</id><!-- Graph Title: \"" . $graph_item["title_cache"] . "\" -->\n");

			}
			fwrite($xml_FH, "          <graph_item_id>" . $graph_item["item_id"] . "</graph_item_id><!-- Comment: \"" . $graph_item["text_format"] . "\"-->\n"); 
			$local_graph_id_prev = $graph_item["local_graph_id"];
		}
		fwrite($xml_FH, "        </graph>\n       </graphs>\n    </customer>\n");
	}else{
		print "No billable graphs found\n";
	}

	fwrite($xml_FH, "  </customers>\n\n");

	/* write footer information */
	fwrite($xml_FH, "</cacti_biller>\n");

	/* close file */
	fclose($xml_FH);

	print "\nConfiguration file written to: $file\n\n";

}



/* 
*****************************************************************************
* Display function
*****************************************************************************
 */

/* get_pretty_nth_value - Get human readable nth percentile string
 *   @arg - $input - Number to process into string
 *   @return - Human readable string
 */
function get_pretty_nth_value($input) {

	$output = $input;
	if (substr($input,1,1) == 1) {
		$output .= "st";
	} elseif (substr($input,1,1) == 2) {
		$output .= "nd";
	} elseif (substr($input,1,1) == 3) {
		$output .= "rd";
	} else {
		$output .= "th";
	}
	$output .= " Percentile";
	return $output;

}

/* unit_multi - return unit muliplier */
function unit_multi($unit) {
	
	if ($unit == "Kb") {
		$multi = 1000;
	} elseif ($unit == "Gb") {
		$multi = 1000000000;
	} elseif ($unit == "Tb") {
		$multi = 1000000000000;
	} else {
		/* Mb default */
		$multi = 1000000;
	}

	return $multi;

}

/* unit_display - return human readable values */
function unit_display($bits) {

	$output = "";

	if (($bits / 1000000000000) > 1 ) {
		$output = round(($bits / 1000000000000), 3) . " Tb";
	}elseif (($bits / 1000000000) > 1 ) {
		$output = round(($bits / 1000000000), 3) . " Gb";
	}elseif (($bits / 1000000) > 1 ) {
		$output = round(($bits / 1000000), 3) . " Mb";
	}elseif (($bits / 1000) > 1 ) {
		$output = round(($bits / 1000), 3) . " Kb";
	}else{
		$output = $bits . " bits";
	}

	return $output;

}


/* 
*****************************************************************************
* Track function
*****************************************************************************
 */

/* read_track - Read and check the track file 
 * @arg - $track_file - Track file
 * @return - boolean - True = good, False = bad
 */
function read_track($track_file, $current_time = 0, $max_cache_age = 0) {
	global $config_track;

	/* sanity check */
	if (empty($track_file)) {
		print "ERROR: Invalid track file\n";
		return false;
	}

	/* set defaults if not passed */
	if (empty($current_time)) {
		$current_time = time();
	}
	if (empty($max_cache_age)) {
		$max_cache_age = 428;
	}

	/* set cache pruning */
	$old_time = mktime(0, 0, 0, date("m", $current_time), date("j", $current_time) - $max_cache_age, date("Y", $current_time));

	/* read track file */
	if (! $config_FH = @fopen($track_file, "r")) {
		$config_track = array();
		return true;
	}
	$data = fread($config_FH, filesize($track_file));
	if (empty($data)) {
		print "ERROR: Track file seems to be empty.\n";
		return false;
	}
	fclose($config_FH);
	$objXML = new xml2Array();
	if (!$track = $objXML->parse($data)) {
	 	exit;
	}

	/* Process XML Data */
	if (isset($track[0]["name"])) {
		if ($track[0]["name"] != "track") {
			print "ERROR: Invalid track file format.\n";
			return false;
		}
	}else{
		print "Invalid track file format\n";
		return false;
	}
	if (isset($track[0]["children"])) {
		foreach ($track[0]["children"] as $child_track) {
			if (!isset($child_track["attrs"]["name"])) {
				print "ERROR: Invalid track item found in track file\n";
				return false;
			}
			if ($child_track["name"] != "customer") {
				print "ERROR: Invalid track file format.\n";
				return false;
			}
			if (isset($child_track["tagData"])) {
				/* Old format */
				debug("Track file old record format found, will be upgraded");
				$config_track[$child_track["attrs"]["name"]]["last_run"] = $child_track["tagData"];
			}else{
				/* New format that includes cache */
				if (isset($child_track["children"])) {
					foreach ($child_track["children"] as $child_track_new) {
						if ($child_track_new["name"] == "last_run") {
							$config_track[$child_track["attrs"]["name"]]["last_run"] = $child_track_new["tagData"];
						}
						if ($child_track_new["name"] == "notification") {
							$config_track[$child_track["attrs"]["name"]]["notification"][$child_track_new["attrs"]["graph_id"]] = $child_track_new["tagData"];
						}
						if ($child_track_new["name"] == "cache") {
							if (isset($child_track_new["children"])) {
								foreach ($child_track_new["children"] as $child_cache_item) {
									if ($child_cache_item["name"] == "item") {
										$cache_date = "";
										$graph_id = "";
										$graph_item_id = "";
										/* Discard non-numeric values */
										if (is_numeric($child_cache_item["tagData"])) {
											foreach ($child_cache_item["attrs"] as $key => $value) {
												if ($key == "date") {
													$cache_date = $value;
												}
												if ($key == "start") {
													$cache_start = $value;
												}
												if ($key == "graph_id") {
													$graph_id = $value;
												}
												if ($key == "graph_item_id") {
													$graph_item_id = $value;
												}
											}
											/* Do not read into memory values which are older than a certain time - cache pruning */
											if ($cache_date > $old_time) {
												$config_track[$child_track["attrs"]["name"]][$graph_id][$graph_item_id][$cache_start][$cache_date] = $child_cache_item["tagData"];
											}
										}
									}
								}
							}
						}
					}	
				}else{
					print "ERROR: Invalid track file format.\n";
					return false;
				}
			}
		}
	}else{
		$config_track = array();
	}
	$track = array();

	return true;

}

/* write_track - Write the track file off of the config_track array
 * @arg - $track - config_track array
 * @return - boolean - True = good, False = bad
 */
function write_track ($track, $file) {
	global $config;

	/* define XML encoding */
	$XMLEncoding = "UTF-8";
	if ($config["cacti_server_os"] == "WIN") {
		$XMLEncoding = "ISO-8859-1";
	}

	/* Open file */
	if (!$track_FH = fopen($file, "w")) {
		print "ERROR: Unable to open file for writing: $file\n";
		return false;
	}

	/* Write header */
	fwrite($track_FH, "<?xml version=\"1.0\" encoding=\"" . $XMLEncoding . "\" standalone=\"yes\"?");
	fwrite($track_FH, ">\n");
	fwrite($track_FH, "<track>\n");

	/* Write contents */
	foreach ($track as $customer => $data) {
		fwrite($track_FH, "  <customer name=\"" . xml_character_encode($customer) . "\">\n");
		foreach ($data as $key => $items) {
			if ($key == "last_run") {
				fwrite($track_FH, "    <last_run>" . xml_character_encode($items) . "</last_run>\n");
			}elseif ($key == "notification") {
				foreach ($items as $graph_id => $value) {
					fwrite($track_FH, "    <notification graph_id=\"" . xml_character_encode($graph_id) . "\">" . xml_character_encode($value) . "</notification>\n");
				}
			}else{
				fwrite($track_FH, "    <cache>\n");
				foreach ($items as $graph_item_id => $graph_item_id_data) {
					foreach ($graph_item_id_data as $start => $cache) {
						foreach ($cache as $timestamp => $value) {
							fwrite($track_FH, "      <item ");
							fwrite($track_FH, "start=\""  . xml_character_encode($start) . "\" ");
							fwrite($track_FH, "date=\""  . xml_character_encode($timestamp) . "\" ");
							fwrite($track_FH, "graph_id=\"" . xml_character_encode($key) . "\" ");
							fwrite($track_FH, "graph_item_id=\"" . xml_character_encode($graph_item_id) . "\">");
							fwrite($track_FH, xml_character_encode($value));
							fwrite($track_FH, "</item>\n");
						}
					}
				}
				fwrite($track_FH, "    </cache>\n");
			}
		}
		fwrite($track_FH, "  </customer>\n");
	}

	/* Write footer */
	fwrite($track_FH, "</track>\n");

	/* Close file */
	fclose($track_FH);

	return true;

}


/* 
*****************************************************************************
* General Functions
*****************************************************************************
 */

/* list_billable() - prints a list of graphs that have graph variable could be used for billing purposes */
function list_billable() {
	
	$graph_items = db_fetch_assoc("SELECT 
			i.id AS item_id, 
			i.local_graph_id, 
			i.task_item_id, 
			i.text_format, 
			g.id AS graph_id, 
			g.title_cache,
			d.local_data_id,
			d.data_source_name
		FROM 
			graph_templates_item AS i, 
			graph_templates_graph AS g,
			data_template_rrd as d
		WHERE 
			i.local_graph_id = g.local_graph_id AND 
			i.task_item_id = d.id AND
			i.local_graph_id <> 0 AND 
			i.task_item_id <> 0 AND 
			i.graph_type_id = 1 AND 
			text_format LIKE '%|%|%'
		ORDER BY
			i.local_graph_id;
		");

	$local_graph_id_prev = 0;
	$count_graph = 0;
	$count_graph_item = 0;
	$item_type = "N/A";
	$match = array();
	if (sizeof($graph_items) > 0) {
		printf("\n%-80s\n","Cacti Billable Graphs List");
		printf("%'=80s\n","");
		printf("%-10s%-70s\n","Graph ID","Graph Title");
		printf("%2s%'--78s\n","","");
		printf("%2s%-7s%-25s%-46s\n","","ID","Item Type","Example Output");
		printf("%'=80s\n","");
		foreach ($graph_items as $graph_item) {
			$item_type = "N/A";
			$match = array();
			if (preg_match('/\|sum\:.*\|/',$graph_item["text_format"]) > 0) { 
				$item_type = "Bandwidth Summation";
			} elseif (preg_match('/\|([0-9]{1,2})\:[\:0-9a-z_]+\|/',$graph_item["text_format"], $match) > 0) { 
				$item_type = get_pretty_nth_value($match[1]);
			}
			if ($graph_item["local_graph_id"] <> $local_graph_id_prev) {	
				if ($local_graph_id_prev <> 0) {
					printf("%'=80s\n","");
				}
				printf("%-10s%-80s\n", $graph_item["local_graph_id"], $graph_item["title_cache"]);
				printf("%2s%'--78s\n","","");
				$count_graph++;
			}
			$count_graph_item++;
			printf("%2s%-7s%-25s%-46s\n","", $graph_item["item_id"],$item_type, process_variables($graph_item["text_format"],$graph_item));
			$local_graph_id_prev = $graph_item["local_graph_id"];
		}
		printf("%'=80s\n","");
		printf("Totals: Graphs: %d Items: %d\n\n", $count_graph, $count_graph_item);
	} else {
		print "\n No items found \n";
	}
	
}

/* info_config - Configuration with additional information for troubleshooting/informational purposes
 *   @arg - $config_customers - Configuration Customers
 *   @arg - $config_defaults - Configuration Defaults
 *   @arg - $config_track - Track data
 *   @arg - $config_file - Configuration file location
 *   @arg - $track_file - Track file location 
 *   @arg - $date_format - Date format of date output
 *   @return - boolean - True = good, False = bad
 */
function info_config($config_customers, $config_defaults, $config_track, $config_file, $track_file, $date_format = "Y/m/d H:i:s") {

	print "\nCacti ISP Billing Customer Information\n";
	print "-------------------------------------------------------------------------------\n";
	print "  Customer configuration file: " . $config_file . "\n";
	print "  Customer time tracking file: " . $track_file . "\n";
	print "===============================================================================\n";

	foreach ($config_customers as $customer_key => $customer) {

		/* Locate Billing Timeframe - Defined per customer or as a default */
		if (isset($customer["billing_timeframe"])) {
			$billing_timeframe = $customer["billing_timeframe"];
		}else{
			$billing_timeframe = $config_defaults["billing_timeframe"];
		}

		/* Customer Header */
		print "  Customer: " . $customer["description"] . "\n";
		print "  -----------------------------------------------------------------------------\n";

		/* Billing timeframe */
		print "    Billing Information:\n      Interval: " . $billing_timeframe["type"];
		if ((isset($billing_timeframe["every"])) && ($billing_timeframe["type"] != "bi-monthly")) {
			if ($billing_timeframe["every"] > 1) {
				print "  Every: " . $billing_timeframe["every"];
			}
		}
		if ((isset($billing_timeframe["day"])) && ($billing_timeframe["type"] == "monthly")) {
			print "  Day: " . $billing_timeframe["day"];
		}
		print "\n";
		if (isset($billing_timeframe["start_date"])) {
			print "      Start date: " . $billing_timeframe["start_date"] . "\n";
		}
		if (isset($config_track[$customer["description"]]["last_run"])) {
			print "      Last run date: " . date($date_format, $config_track[$customer["description"]]["last_run"]) . "\n";
		}else{
			print "      Last run date: N/A\n";
		}
		print "  -----------------------------------------------------------------------------\n";


		/* Emails */
		print "    Email:\n";
		if (isset($customer["email"])) {
			$emails = $customer["email"];
		}else{
			$emails = $config_defaults["email"];
		}
		foreach ($emails as $email) {
			print "      Address: " . $email["address"] . "   ";
			if (strtolower($email["address"]) != "null") {
				print "Type: " . $email["type"] . "  ";
				if ($email["html"] == 1) {
					print "HTML: yes  ";
				}else{
					print "HTML: no  ";
				}
				if ($email["type"] == "all") {
					if ($email["csv"] == 1) {
						print "CSV: yes";
					}else{
						print "CSV: no";
					}
				}
			}
			print "\n";

		}
		print "  -----------------------------------------------------------------------------\n";

		/* File export */
		if (isset($customer["file"])) {
			print "    File Export: \n      Path: " . $customer["file"]["path"] . "\n";
			print "  -----------------------------------------------------------------------------\n";
		}elseif (isset($config_defaults["file"])) {
			print "    File Export: \n      Path: " . $config_defaults["file"]["path"] . "\n";
			print "  -----------------------------------------------------------------------------\n";
		}

		/* Currency - Used in graphs */
		$currency = $config_defaults["currency"];
		if (isset($customer["currency"])) {
			if (isset($customer["currency"]["pre"])) {
				$currency["pre"] = $customer["currency"]["pre"];
			}
			if (isset($customer["currency"]["post"])) {
				$currency["post"] = $customer["currency"]["post"];
			}
			if (isset($customer["currency"]["precision"])) {
				$currency["precision"] = $customer["currency"]["precision"];
			}
		}
		
		/* Graphs */
		print "    Graphs:\n";
		foreach ($customer["graphs"] as $graph_key => $graph) {
			if (isset($graph["graph_item_id"])) {
				print "      Graph ID: " . $graph["id"] . " Item(s): " . join(", ", $graph["graph_item_id"]) . "\n";
			}else{  
				print "      Graph ID: " . $graph["id"] . " Item(s): ALL\n";
			}
			if (isset($graph["rate"])) {
				print "        Rate Type: Regular at " . $currency["pre"];
				print $graph["rate"]["amount"];
				print " per " . $graph["rate"]["unit"] . $currency["post"];
				if (isset($graph["rate"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $graph["rate"]["minimum"] . $currency["post"];
				}
				print "\n";
			} elseif (isset($graph["rate_fixed"])) {
				print "        Rate Type: Fixed at " . $currency["pre"];
				print $graph["rate_fixed"]["amount"] . $currency["post"] . "\n";
			} elseif (isset($graph["rate_overage"])) {
				print "        Rate: Committed at " . $currency["pre"];
				print $graph["rate_committed"]["amount"] . $currency["post"];
				print " per " . $graph["rate_committed"]["unit"];
				if (isset($graph["rate_committed"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $graph["rate_committed"]["minimum"] . $currency["post"];
				}
				print "\n";
				print "        Rate: Overage at " . $currency["pre"];
				print $graph["rate_overage"]["amount"] . $currency["post"];
				print " per " . $graph["rate_overage"]["unit"];
				print " with a threshold of " . $graph["rate_overage"]["threshold"] . "\n";
			} elseif (isset($customer["rate"])) {
				print "        Rate: Regular at " . $currency["pre"];
				print $config_customers[$customer_key]["rate"]["amount"] . $currency["post"];
				print " per " . $config_customers[$customer_key]["rate"]["unit"];
				if (isset($config_customers[$customer_key]["rate"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $config_customers[$customer_key]["rate"]["minimum"] . $currency["post"];
				}
				print "\n";
			} elseif (isset($customer["rate_fixed"])) {
				print "        Rate: Fixed at " . $currency["pre"];
				print $config_customers[$customer_key]["rate_fixed"]["amount"] . $currency["post"] . "\n";
			} elseif (isset($customer["rate_overage"])) {
				print "        Rate: Committed at " . $currency["pre"];
				print $config_customers[$customer_key]["rate_committed"]["amount"] . $currency["post"];
				print " per " . $config_customers[$customer_key]["rate_committed"]["unit"];
				if (isset($config_customers[$customer_key]["rate_committed"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $config_customers[$customer_key]["rate_committed"]["minimum"] . $currency["post"];
				}
				print "\n";
				print "        Rate: Overage at " . $currency["pre"];
				print $config_customers[$customer_key]["rate_overage"]["amount"] . $currency["post"];
				print " per " . $config_customers[$customer_key]["rate_overage"]["unit"];
				print " with a threshold of " . $config_customers[$customer_key]["rate_overage"]["threshold"] . "\n";
			} elseif (isset($config_defaults["rate"])) {
				print "        Rate: Regular at " . $currency["pre"];
				print $config_defaults["rate"]["amount"] . $currency["post"];
				print " per " . $config_defaults["rate"]["unit"];
				if (isset($config_defaults["rate"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $config_defaults["rate"]["minimum"] . $currency["post"];
				}
				print "\n";
			} elseif (isset($config_defaults["rate_fixed"])) {
				print "        Rate: Fixed at " . $currency["pre"];
				print $config_defaults["rate_fixed"]["amount"] . $currency["post"] . "\n";
			} elseif (isset($config_defaults["rate_overage"])) {
				print "        Rate Type: Committed at " . $currency["pre"];
				print $config_defaults["rate_committed"]["amount"] . $currency["post"];
				print " per " . $config_defaults["rate_committed"]["unit"];
				if (isset($config_defaults["rate_committed"]["minimum"])) {
					print " with a minimum charge of " . $currency["pre"] . $config_defaults["rate_committed"]["minimum"] . $currency["post"];
				}
				print "\n";
				print "        Rate: Overage at ";
				print $config_defaults["rate_overage"]["amount"] . $currency["post"];
				print " per " . $config_defaults["rate_overage"]["unit"];
				print " with a threshold of " . $config_defaults["rate_overage"]["threshold"] . "\n";
			}
		}
		print "===============================================================================\n";

	}

	print "Total Customers: " . sizeof($config_customers) . "\n\n";
	return true;

}

/* write_tech - Write out tech support file
 *  @arg - $version - ISP billing version
 *  @arg - $file - File to write tech to
 *  @return - undef
 */
function write_tech ($version, $file = "isp_billing_tech_output.txt") {
	global $config;

	if (file_exists($file)) {
		print "\nERROR: Tech file exists, please move or delete: " . $file . "\n\n";
		exit;
	}

	if (($fh = fopen($file, "w")) === false) {
		print "\nERROR: Unable to open file for write: " . $file . "\n\n";
		exit;
	}

	fwrite($fh, "Cacti ISP Billing Technical Support Output\n");
	fwrite($fh, "================================================================================\n");
	fwrite($fh, "Date: " . date("r") . "\n");
	fwrite($fh, "Cacti ISP Billing Version: " . $version . "\n");
	fwrite($fh, "Cacti Version: " . $config["cacti_version"] . "\n");
	fwrite($fh, "Cacti OS: " . $config["cacti_server_os"] . "\n");
	fwrite($fh, "PHP Version: " . phpversion() . "\n");
	fwrite($fh, "PHP OS: " . PHP_OS . "\n");
	if (function_exists("php_uname")) {
		fwrite($fh, "PHP uname: " . php_uname() . "\n");
	}
	fwrite($fh, "PHP Information:\n");
	fwrite($fh, "================================================================================\n");

	ob_start();                                                                                                       
	phpinfo();                                                                                                        
	fwrite($fh, ob_get_contents() . "\n");
	ob_end_clean();       

	fclose($fh);

	print "\nTech file written to: " . $file . "\n\n";

}

/* display_help - displays the usage of the function */
function display_help ($version = 0) {
	print "Cacti ISP Billing Script, Copyright 2006-2009 - The Cacti Group\nVersion: " . $version . "\n";
	print "usage: -config=[file] -track=[file] [-check] [-build=[file] [-filter=[id]]] [-process] [-list] \n";
	print "       [-email=[email]] [-email_no_html] [-html_no_csv] [-start_date=[date]] \n";
        print "       [-current_time=[date]] [-tech] [-d] [--debug] [-h] [--help] [-v] [--version]\n\n";
	print "-config=[file]       - Billing configuration file\n";
	print "-track=[file]        - Date tracking file\n";
	print "-track_no_write      - Do not update the date tracking file\n";
	print "-track_clear_cache   - Clear threshold tracking cache from track file\n";
	print "-check               - Check billing configuration file\n";
	print "-build=[file]        - Build example configuration file from system, supplying filename is \n";
	print "                       optional, default example.xml\n";
	print "-filter=[id]         - Only used by the build command to limit configuration build to the supplied\n";
	print "                       graph ids, comma delimited\n";
	print "-info                - Display information on the billing configuration file\n";
	print "-list                - Display list of graphs and titles that are billable\n";
	print "-email=[email]       - Override configuration email addresses, all customer reports will be\n";
	print "                       emailed to the supplied email\n";
	print "-email_no_html       - Only used when email override enabled, globally set no html emails \n";
	print "-email_no_csv        - Only used when email override enabled, globally set no csv attachments\n";
	print "-start_date=[date]   - Used to override track date and start date for testing and reruns\n";
	print "-current_time=[date] - Used to override current time for testing and reruns\n";
	print "-tech                - Writes out technical support file\n";
	print "-d --debug           - Display verbose output during execution\n";
	print "-v --version         - Display this help message\n";
	print "-h --help            - Display this help message\n\n";
}

/* debug - Display debug output to stdout */
function debug($message, $linefeed = 1) {
	global $debug;

	if ($debug) {
		if ($linefeed == 0) {
			print "DEBUG: " . $message . "\r";
		}else{
			print "DEBUG: " . $message . "\n";
		}
	}
}

?>
