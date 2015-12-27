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

/* Global Date format, php date function format string */
$date_format = "Y/m/d H:i:s";

/* Temporary directory for file and image attachments */
$tmp_dir = dirname(__FILE__) . "/tmp/";

/* Email type PHP or SMTP */
$mailer_type = "PHP";

/* SMTP Server */
$mailer_smtp_server = "localhost";

/* SMTP Port */
$mailer_smtp_port = "25";

/* SMTP Auth */
$mailer_smtp_username = "";
$mailer_smtp_password = "";

/* Max Cache Age in Days */
$max_cache_age = 428;

/* Nth Percentile Notification Interval divider - Takes the total number of days
   in the rate interval and resets notifications every divisor interval */
$notification_interval_divider = 4;

/* ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ####                                                                    ####
 * ####                                                                    ####
 * ####           Nothing needs to be altered after this point             ####
 * ####                                                                    ####
 * ####                                                                    ####
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################
 * ############################################################################ */

/* DO NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
	   die("<br><strong>This script is only meant to run at the command line.</strong>");
}

ini_set("max_execution_time", "0");
ini_set("memory_limit", "1024M");
$no_http_headers = true;
$version = "1.0.13";

/* Check for required functions */
if (!function_exists('xml_parser_create')) {
	print "ERROR: PHP XML modules does not appear to be loaded, please correct the problem to proceed\n\n";
	exit;
}

/* Set Sessions information */
session_cache_expire(1);

/* Include the configuration to get database access */
if (file_exists(dirname(__FILE__) . "/../include/global.php")) {
	/* 0.8.7+ include */
	@include(dirname(__FILE__) . "/../include/global.php");
} else {
	/* 0.8.6j- include */
	@include(dirname(__FILE__) . "/../include/config.php");
}

/* Check that file was included correctly */
if (! isset($config["cacti_version"])) {
	print "ERROR: Unable to include Cacti configuration\n";
	print "ERROR: This script is designed to run from a sub-directory of your Cacti installation\n";
	exit;
}

/* Check version */
switch (substr($config["cacti_version"],0,5)) {
	case "0.8.6":
		if (ord(substr($config["cacti_version"],5,1)) < 104) {
			print "ERROR: This script requires Cacti version 0.8.6h or greater\n\n";
			exit;
		}
		break;
	case "0.8.7":
		break;
	case "0.8.8":
		break;
	default:
		print "Error: This script required Cacti version 0.8.6h to 0.8.8x\n\n";
		exit;
}

/* Process the rest of the Cacti includes */
include(dirname(__FILE__) . "/../lib/rrd.php");
include(dirname(__FILE__) . "/../lib/graph_variables.php");
include(dirname(__FILE__) . "/../lib/export.php");

/* ISP Billing includes */
include(dirname(__FILE__) . "/lib/functions.php");
include(dirname(__FILE__) . "/lib/xml.php");
include(dirname(__FILE__) . "/lib/mailer.php");

/* 
*****************************************************************************
* Process calling arguments 
*****************************************************************************
 */
$parms = $_SERVER["argv"];
array_shift($parms);

/* Utility requires input parameters */
if (sizeof($parms) == 0) {
	print "ERROR: You must supply input parameters\n\n";
	display_help($version);
	exit;
}

/* Parse command line options */
$debug = FALSE;
$cmd_config = "";
$cmd_track = "";
$cmd_check = FALSE;
$cmd_info = FALSE;
$cmd_track_no_write = FALSE;
$cmd_track_clear_cache = FALSE;
$cmd_current_time = "";
$cmd_start_date = "";
$cmd_filter = "";
$cmd_build = FALSE;
$cmd_build_file = "";
$cmd_email = "";
$cmd_email_html = 1;
$cmd_email_csv = 1;
$config_defaults = array();
$config_globals = array();
$config_customers = array();
$config_track = array();
$XMLEncoding = "UTF-8";
$TargetEncoding = "UTF-8";
foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-config":
		$cmd_config = $value;
		break;
	case "-track":
		$cmd_track = $value;
		break;
	case "-track_no_write":
		$cmd_track_no_write = TRUE;
		break;
	case "-track_clear_cache":
		$cmd_track_clear_cache = TRUE;
		break;
	case "-list":
		list_billable();
		exit;
		break;
	case "-info":
		$cmd_info = TRUE;
		break;
	case "-check":
		$cmd_check = TRUE;
		break;
	case "-build":
		$cmd_build_file = $value;
		$cmd_build = TRUE;
		break;
	case "-filter":
		$cmd_filter = $value;
		break;
	case "-email":
		$cmd_email = $value;
		break;
	case "-email_no_html":
		$cmd_email_html = 0;
		break;
	case "-email_no_csv":
		$cmd_email_csv = 0;
		break;
	case "-current_time":
		$cmd_current_time = $value;
		break;
	case "-start_date":
		$cmd_start_date = $value;
		break;
	case "-d":
		$debug = TRUE;
		break;
	case "--debug":
		$debug = TRUE;
		break;
	case "-h":
		display_help($version);
		exit;
	case "-v":
		display_help($version);
		exit;
	case "--version":
		display_help($version);
		exit;
	case "--help":
		display_help($version);
		exit;
	case "-tech":
		write_tech($version);
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help($version);
		exit;
	}
}

/* Display ISP Billing version and Cacti Version */
if ($debug) {

	debug("Starting at " . date($date_format));
	debug("Cacti ISP Billing Version: " . $version);
	debug("Cacti Version: " . $config["cacti_version"]);
	debug("Cacti OS: " . $config["cacti_server_os"]);
	debug("PHP Version: " . phpversion());
	debug("PHP OS: " . PHP_OS);
	if (function_exists("php_uname")) {
		debug("PHP uname: " . php_uname());
	}
}

/* Build a config file if requested */
if ($cmd_build) {
	build_config($cmd_build_file, $cmd_filter, $cmd_email, $cmd_email_html, $cmd_email_csv);
	exit;
}


/* Check command line options */
if (empty($cmd_config)) {
	print "ERROR: Required command line option, -config not specified.\n";
	display_help($version);
	exit;
}

/* Check for track file */
if (empty($cmd_track)) {
	print "ERROR: Required command line option -track not specified.\n";
	display_help($version);
	exit;
}

/* Parse the configuration */
debug("Checking configuration file");
if (! read_config($cmd_config)) {
	print "ERROR: Configuration issue.\n";
	exit;
}
if ($cmd_check) {
	print "Configurations file checked successfully.\n";
	exit;
}

/* Get current time, so that all customers are processed against the same time */
if (empty($cmd_current_time)) {
	$current_time = time();
}else{
	debug("Current time override enabled");
	if (strtotime($cmd_current_time) == FALSE) {
		print "Error: Current time override is not a valid date\n";
		exit;
	}
	$current_time = strtotime($cmd_current_time);
}
debug("Current Time: " . date($date_format, $current_time));

/* Parse the track file */
debug("Parsing tracking file");
if ($cmd_track_clear_cache == TRUE) {
	$max_cache_age = -1;
	debug("Clearing threshold tracking cache from track file");
}
if (!read_track($cmd_track, $current_time, $max_cache_age)) {
	print "ERROR: Unable to process track file.\n";
	exit;
}
if ($cmd_track_clear_cache == TRUE) {
        /* Write the track file */
	if (write_track($config_track, $cmd_track)) {
		print "Track file threshold tracking cache cleared\n";
	}else{
		print "ERROR: Unable to write track file";
	}
	exit;
}


/* Show configuration info if requested */
if ($cmd_info) {
	info_config($config_customers, $config_defaults, $config_track, $cmd_config, $cmd_track, $date_format);
	exit;
}

/* Check start_date command line option */
if (! empty($cmd_start_date)) {
	debug("Start date override enabled");
	if (strtotime($cmd_start_date) == FALSE) {
		print "Error: Start date override is not a valid date\n";
		exit;
	}
	$cmd_start_date = strtotime($cmd_start_date);
	debug("Start date: " . date($date_format, $cmd_start_date));
}

/*
*****************************************************************************
* Process customers configuration and gather threshold related data 
*****************************************************************************
*/
$customer_time = array();
$email_index = array();
$file_index = array();
$threshold_emails = array();
debug("Processing customers");
foreach ($config_customers as $customer_key => $customer) {
	debug("  Customer: " . $customer["description"]);

	/* Create issues tracking array */
	$config_customers[$customer_key]["issues"] = array();

	/* Get start and end time for data */
	$process_billing = TRUE;
	$process_threshold = FALSE;
	$customer_time = get_start_end_time($customer, $config_track, $config_defaults, $current_time, $date_format, $cmd_start_date, $config_globals["threshold_tracking"]["enabled"]);
	if ($customer_time != FALSE) {
		$config_customers[$customer_key]["timeframe"] = $customer_time;
		if ((isset($customer_time["threshold_start"])) && (isset($customer_time["threshold_end"]))) {
			$process_threshold = TRUE;
			debug("    Threshold tracking enabled");
			debug("      Start(" . $customer_time["threshold_start"] . "): " . date($date_format, $customer_time["threshold_start"]));
			debug("      End(" . $customer_time["threshold_end"] . "): " . date($date_format, $customer_time["threshold_end"]));
		}
		if (($customer_time["start"] == 0) || ($customer_time["end"] == 0)) {
			$process_billing = FALSE;
		}
		foreach ($customer["graphs"] as $graph_key => $graph) {
			$graph_items = "";
			if (isset($graph["graph_item_id"])) {
				foreach ($graph["graph_item_id"] as $graph_item) {
					debug("    Graph: " . $graph["id"] . " Item: " . $graph_item);
				}
				$graph_items = join(",",$graph["graph_item_id"]);
			}else{
				debug("    Graph: " . $graph["id"] . " Items: ALL");
			}

			/* Process graph */
			if ($process_billing) {
				$graph_values = process_graph($graph["id"], $graph_items, $customer_time["start"], $customer_time["end"]);
			}elseif ($process_threshold) {
				$graph_values = process_graph($graph["id"], $graph_items, $customer_time["threshold_start"], $customer_time["threshold_end"]);
			}else{
				$graph_values = array();
			}
			if ((sizeof($graph_values) > 0) && (is_array($graph_values))) {
				if ($debug) { /* just to show progress */
					foreach ($graph_values as $field => $field_data) {
						debug("      Item: " . $field . " Value: " . $field_data["value_text"]);
					}
				}

				/* add customer graph values to processing matrix */
				$config_customers[$customer_key]["graphs"][$graph_key]["values"] = $graph_values;

				/* Graph title needs to be in graph */
				$graph_title_key = array_keys($graph_values);
				$config_customers[$customer_key]["graphs"][$graph_key]["graph_title"] = $graph_values[$graph_title_key[0]]["graph_title"];

				/* Add rates on every graph */
				if ( (! isset($graph["rate"])) && (! isset($graph["rate_overage"])) && (! isset($graph["rate_fixed"])) ) {
					if (isset($customer["rate"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate"] = $customer["rate"];	
					} elseif (isset($customer["rate_fixed"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_fixed"] = $customer["rate_fixed"];
					} elseif (isset($customer["rate_overage"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_committed"] = $customer["rate_committed"];
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_overage"] = $customer["rate_overage"];
					} elseif (isset($config_defaults["rate"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate"] = $config_defaults["rate"];	
					} elseif (isset($config_defaults["rate_fixed"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_fixed"] = $config_defaults["rate_fixed"];	
					} elseif (isset($config_defaults["rate_overage"])) {
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_committed"] = $config_defaults["rate_committed"];
						$config_customers[$customer_key]["graphs"][$graph_key]["rate_overage"] = $config_defaults["rate_overage"];
					}else{
						array_push($config_customers[$customer_key]["issues"], "Unable to locate rate for graph id " . $graph["id"]);
					}
				}

			}else{
				$process_billing = FALSE;
				$process_threshold = FALSE;
				debug("    No graph items to process");
			}
		}
	}else{
		$process_billing = FALSE;
		debug("  + No need to process billing or thresholds for customer");
	}


	/* Process billing is so inclined */
	if ($process_billing) {
		/* Define required elements if not defined */
		if (empty($customer["email"])) {
			$config_customers[$customer_key]["email"] = $config_defaults["email"];
		}

		/* Set file if defined in defaults and not set on customer */
		if (isset($config_defaults["file"])) {
			if (! isset($config_customers[$customer_key]["file"])) {
				$config_customers[$customer_key]["file"] = $config_defaults["file"];
			}
		}
	
		/* Define currency per customer */
		if (! isset($config_customers[$customer_key]["currency"]["pre"])) {
			$config_customers[$customer_key]["currency"]["pre"] = $config_defaults["currency"]["pre"];
		}
		if (! isset($config_customers[$customer_key]["currency"]["post"])) {
			$config_customers[$customer_key]["currency"]["post"] = $config_defaults["currency"]["post"];
		}
		if (! isset($config_customers[$customer_key]["currency"]["precision"])) {
			$config_customers[$customer_key]["currency"]["precision"] = $config_defaults["currency"]["precision"];
		}

		/* Build indexes of email addresses.  Consolidate information to the email addresses that are being sent to */
		if (empty($cmd_email)) {
			foreach ($config_customers[$customer_key]["email"] as $email) {
				if (strtolower($email["address"]) != "null") {
					if (($email["type"] == "all") or ($email["type"] == "billing")) {
						if (isset($email_index[ $email["address"] . $email["html"] . $email["csv"] ])) {
							array_push($email_index[ $email["address"] . $email["html"] . $email["csv"] ]["customers"], $customer_key);
						}else{
							$email_index[ $email["address"] . $email["html"] . $email["csv"] ]["customers"] = array( $customer_key );
						}
						$email_index[ $email["address"] . $email["html"] . $email["csv"] ]["address"] = $email["address"];
						$email_index[ $email["address"] . $email["html"] . $email["csv"] ]["html"] = $email["html"];
						$email_index[ $email["address"] . $email["html"] . $email["csv"] ]["csv"] = $email["csv"];
					}
				}
			}
		}else{
			if (isset($email_index[$cmd_email])) {
				array_push($email_index[$cmd_email]["customers"], $customer_key);
			}else{
				$email_index[$cmd_email]["customers"] = array( $customer_key );
			}
			$email_index[$cmd_email]["address"] = $cmd_email;
			$email_index[$cmd_email]["html"] = $cmd_email_html;
			$email_index[$cmd_email]["csv"] = $cmd_email_csv;
		
		}

		/* Build indexes of files. We consolidate information to build files together (future support for multiple file formats) */
		if (isset($config_customers[$customer_key]["file"])) {
			$file_key = $config_customers[$customer_key]["file"]["path"] . $config_customers[$customer_key]["file"]["format"] . $config_customers[$customer_key]["file"]["process"];
			if (isset($file_index[$file_key])) {
				array_push($file_index[$file_key]["customers"], $customer_key);
			}else{
				$file_index[$file_key]["customers"] = array( $customer_key );
			}
			$file_index[$file_key]["path"] = $config_customers[$customer_key]["file"]["path"];
			$file_index[$file_key]["format"] = $config_customers[$customer_key]["file"]["format"];
			$file_index[$file_key]["process"] = $config_customers[$customer_key]["file"]["process"];
		}

	}

	/* Process threshold data is so inclined */
	if ($process_threshold) {
		debug("    Processing threshold data");

		/* Define required elements if not defined */
		if (empty($customer["email"])) {
			$config_customers[$customer_key]["email"] = $config_defaults["email"];
		}

		/* Gather emails for threshold alerting - if we are not processing billing */
		if ($process_billing == FALSE) {
			if (empty($cmd_email)) {
				foreach ($config_customers[$customer_key]["email"] as $email) {
					if (strtolower($email["address"]) != "null") {
						if (($email["type"] == "all") or ($email["type"] == "threshold")) {
							if (isset($threshold_emails[$customer_key])) {
								if (! in_array($email["address"], $threshold_emails[$customer_key])) {
									array_push($threshold_emails[$customer_key], $email["address"]);
								}
							}else{
								$threshold_emails[$customer_key] = array( $email["address"] );
							}
						}
					}
				}
			}else{
				$threshold_emails[$customer_key] = array( $cmd_email );
			}
		}

		debug("      Processing graph data");
		foreach ($config_customers[$customer_key]["graphs"] as $graph_key => $graph) {
			if (isset($graph["rate_overage"])) {
				/* We have a thresold to process */
				$graph_items = "";
				if (isset($graph["graph_item_id"])) {
					foreach ($graph["graph_item_id"] as $graph_item) {
						debug("        Graph: " . $graph["id"] . " Item: " . $graph_item);
					}
					$graph_items = join(",",$graph["graph_item_id"]);
				}else{
					debug("      Graph: " . $graph["id"] . " Items: ALL");
				}

				/* Get the threshold data */
				$graph_threshold = process_graph_threshold($customer_key, $graph["id"], $graph_items, $customer_time["threshold_start"], $customer_time["threshold_end"], $current_time, $graph["rate_overage"]["threshold"]);

				/* append threshold data to customer data */
				if (sizeof($graph_threshold) > 0) {
					foreach ($graph_threshold as $graph_item_id => $threshold_data) {
						if (isset($threshold_data["value"])) {
							$config_customers[$customer_key]["graphs"][$graph_key]["values"][$graph_item_id]["threshold"] = $threshold_data;
						}
					}
				}
			}
		}
	}
}


/* 
*****************************************************************************
* Process billing emails 
*****************************************************************************
*/
if (sizeof($email_index) > 0) {
	debug("Emailing billing reports");
} else {
	debug("No billing reports to email");
}
$images = array();
$error = false;
foreach ($email_index as $email) {

	/* Create email object */
	$obj_mail = new Mailer(array( 
		"WordWrap"=>"132",
		"Type"=>$mailer_type,
		"SMTP_Host"=>$mailer_smtp_server,
		"SMTP_Port"=>$mailer_smtp_port,
		"SMTP_Username"=>$mailer_smtp_username,
		"SMTP_Password"=>$mailer_smtp_password,
		"CharSet"=>$TargetEncoding
		));

	/* Set email message properties */
	$obj_mail->header_set("From", $obj_mail->email_format($config_globals["email"]["title"] ,$config_globals["email"]["return_address"]));
	$obj_mail->header_set("Subject", $config_globals["email"]["subject"] . " - " . date($date_format));
	foreach (explode(",", $email["address"]) as $address) {
		$obj_mail->header_set("To", $address);
	}
	
	/* Initialize email parts */
	$csv_file = "\"Customer Description\",\"Billing Period Start\",\"Billing Period End\",\"Graph Title\",\"Type\",\"Interval\",\"Every\",\"Rate Type\",\"Rate Amount\",\"Rate Unit\",\"Bits\",\"Total\",\"Overage Occurred\"\n";
	$html_part = "<html><head><title>" . $config_globals["email"]["title"] . "</title></head><body bg=\"#FFFFFF\">\n<h2>" . $config_globals["email"]["title"] . "</h2>\n";
	$text_part = $config_globals["email"]["title"] . "\n\n";

	debug("  Gathering billing reports for email: " . $email["address"]);
	if ($email["html"] == 1) {
		debug("    HTML: TRUE");
	}else{
		debug("    HTML: FALSE");
	}
	if ($email["csv"] == 1) {
		debug("    CSV: TRUE");
	}else{
		debug("    CSV: FALSE");
	}
	foreach ($email["customers"] as $customer) {
		debug("    Customer: " . $config_customers[$customer]["description"]);

		/* Locate Billing Timeframe - Defined per customer or as a default */
		if (isset($config_customers[$customer]["billing_timeframe"])) {
			$billing_timeframe = $config_customers[$customer]["billing_timeframe"];
		}else{
			$billing_timeframe = $config_defaults["billing_timeframe"];
		}

		/* Process customer information */
		$html_part .= "<hr noshade>\n<div>\n<font size=\"+2\"><b>Customer:</b> " .  $config_customers[$customer]["description"] . "</font><br>\n";
		$text_part .= "Customer: " .  $config_customers[$customer]["description"] . "\n";

		/* Display Timeframes */
		$html_part .= "<b>Billing Period Start:</b> " . $config_customers[$customer]["timeframe"]["start_formatted"] . "<br>\n";
		$text_part .= "Billing Period Start: " . $config_customers[$customer]["timeframe"]["start_formatted"] . "\n";
		$html_part .= "<b>Billing Period End:</b> " . $config_customers[$customer]["timeframe"]["end_formatted"] . "<br>\n";
		$text_part .= "Billing Period End: " . $config_customers[$customer]["timeframe"]["end_formatted"] . "\n";

		$html_part .= "<b>Billing Interval:</b> " . $billing_timeframe["type"];
		$text_part .= "Billing Interval: " . $billing_timeframe["type"];
		if (isset($billing_timeframe["every"])) {
			if ($billing_timeframe["every"] > 1) {
				$html_part .= " <b>Every:</b> " . $billing_timeframe["every"] . "<br><br>\n";
				$text_part .= " Every: " . $billing_timeframe["every"] . "\n\n";
			}
		}
		$html_part .= "<br><br>\n";
		$text_part .= "\n\n";

		/* Display Billing Issues */
		if (sizeof($config_customers[$customer]["issues"]) > 0) {
			$html_part .= "<font color=\"red\"><b>Please investigate the following issues found while processing:</b></font>\n<ul>";
			$text_part .= "Please investigate the following issues found while processing:\n";
			foreach ($config_customers[$customer]["issues"] as $issue) {
				$html_part .= "<li>" . $issue . "</li>\n";
				$text_part .= "  - " . $issue . "\n";
			}
			$html_part .= "</ul><br>\n";
			$text_part .= "\n";
		}

		/* Display graphs and billing information */
		if (sizeof($config_customers[$customer]["graphs"]) > 0) {
			$customer_total = 0;
			foreach ($config_customers[$customer]["graphs"] as $graph_key => $graph) {

				if ($email["html"] == 1) {
					/* Attach graph image for html formatted message - unique filename is important for proper caching */
					$image_name = "graph_" . $customer . "_" . $graph["id"] . "_" . 
						$config_customers[$customer]["timeframe"]["start"] . "_" . 
						$config_customers[$customer]["timeframe"]["end"] . ".png";
					$image_path = $tmp_dir . $image_name;
					/* Check that we haven't already generated this image */
					if (file_exists($image_path)) {
						debug("      Using cached image: " . $image_name);
						if (($image_file = $obj_mail->read_file($image_path)) === false) {
							print "ERROR: Unable to read cached image: " . $obj_mail->error() . "\n";
							exit;
						}
					}else{
						debug("      Generating image: " . $image_name);
						$rrdtool_pipe = "";
						$image_file = rrdtool_function_graph($graph["id"], "", array( 
							"graph_start"=>$config_customers[$customer]["timeframe"]["start"], 
							"graph_end"=>$config_customers[$customer]["timeframe"]["end"],
							"output_flag"=>RRDTOOL_OUTPUT_STDOUT),
							$rrdtool_pipe
						); 
						if (! $image_FH = fopen($image_path, "w")) {
							print "ERROR: Unable to open file: " . $image_name . "\n";
							exit;
						}
						fwrite($image_FH, $image_file);
						fclose($image_FH);
						$images[$image_path] = "";
					}
					if (strlen($image_file) > 0) {
						$obj_mail->Attach($image_file, $image_name, "image/png", "inline", $image_name);
					}

				$html_part .= "<table border=\"1\" cellspacing=\"0\">\n";
				$html_part .= "<tr><td colspan=\"6\"><font size=\"+1\"><b>" . $graph["graph_title"] . "</b></font></td></tr>\n";
				$html_part .= "<tr><td colspan=\"6\" align=\"center\"><img src=\"cid:" . $image_name . "\"></td></tr>\n";
				}
				$text_part .= "  Graph: " . $graph["graph_title"] . "\n";

				/* Summarize Graph information */
				$html_part .= "<tr><th>Type</th><th>Text</th><th>Bits</th><th>Rate Type</th><th>Rate Amount</th><th>Total</th></tr>\n";
				$grand_total = 0;
				foreach ($graph["values"] as $item) {
					if (isset($graph["rate"])) {
						/* Regular rate */
						$total = calculate_rate($graph, "regular", $item["value_bits"]);			
						$grand_total += round($total, 2);
						$bits = $item["value_bits"];
						$html_part .= "<tr>\n";
						$html_part .= "  <td>" . $item["type"] . "</td>\n";
						$html_part .= "  <td>" . $item["value_text"] . "</td>\n";
						$html_part .= "  <td>" . number_format(($bits / unit_multi($graph["rate"]["unit"])), 2) . " " . $graph["rate"]["unit"] . "</td>\n";
						if (isset($graph["rate"]["minimum"])) {
							$html_part .= "  <td>Regular <br><i>(Minimum: " . $config_customers[$customer]["currency"]["pre"];
							$html_part .= number_format($graph["rate"]["minimum"], $config_customers[$customer]["currency"]["precision"]);
							$html_part .= $config_customers[$customer]["currency"]["post"] .")</i></td>\n";
						}else{
							$html_part .= "  <td>Regular</td>\n";
						}
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"] . $graph["rate"]["amount"];
						$html_part .= $config_customers[$customer]["currency"]["post"]. " per " . $graph["rate"]["unit"] . "</td>\n";
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"];
						$html_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$html_part .= $config_customers[$customer]["currency"]["post"] . "</td>\n";
						$html_part .= "</tr>\n";

						$text_part .= "    Type: " . $item["type"] . "\n";
						$text_part .= "    Text: " . $item["value_text"] . "\n";
						$text_part .= "    Bits: " . number_format(($bits / unit_multi($graph["rate"]["unit"])), 2) . " " . $graph["rate"]["unit"] . "\n";
						if (isset($graph["rate"]["minimum"])) {
							$text_part .= "    Rate Type: Regular (Minimum: " . $config_customers[$customer]["currency"]["pre"];
							$text_part .= number_format($graph["rate"]["minimum"], $config_customers[$customer]["currency"]["precision"]);
							$text_part .= $config_customers[$customer]["currency"]["post"] .")\n";
						}else{
							$text_part .= "    Rate Type: Regular\n";
						}
						$text_part .= "    Rate Amount: " . $config_customers[$customer]["currency"]["pre"];
						$text_part .= number_format($graph["rate"]["amount"], $config_customers[$customer]["currency"]["precision"]);
						$text_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate"]["unit"] . "\n";
						$text_part .= "    Total: \$" . number_format($total,2) . "\n\n";
	
						$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
						$csv_file .= "\"" . $graph["graph_title"] . "\",";
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Regular\",";
						$csv_file .= "\"" . $graph["rate"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"\n";

					} elseif (isset($graph["rate_fixed"])) {
						/* Fixed rate */
						$total = calculate_rate($graph, "fixed", $item["value_bits"]);			
						$grand_total += round($total, 2);
						$bits = $item["value_bits"];
						$html_part .= "<tr>\n";
						$html_part .= "  <td>" . $item["type"] . "</td>\n";
						$html_part .= "  <td>" . $item["value_text"] . "</td>\n";
						$html_part .= "  <td>" . number_format(($bits / unit_multi($graph["rate_fixed"]["unit"])), 2) . " " . $graph["rate_fixed"]["unit"] . "</td>\n";
						$html_part .= "  <td>Fixed</td>\n";
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"] . $graph["rate_fixed"]["amount"];
						$html_part .= $config_customers[$customer]["currency"]["post"]. "</td>\n";
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"];
						$html_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$html_part .= $config_customers[$customer]["currency"]["post"] . "</td>\n";
						$html_part .= "</tr>\n";

						$text_part .= "    Type: " . $item["type"] . "\n";
						$text_part .= "    Text: " . $item["value_text"] . "\n";
						$text_part .= "    Bits: " . number_format(($bits / unit_multi($graph["rate_fixed"]["unit"])), 2) . " " . $graph["rate_fixed"]["unit"] . "\n";
						$text_part .= "    Rate Type: Fixed\n";
						$text_part .= "    Rate Amount: " . $config_customers[$customer]["currency"]["pre"];
						$text_part .= number_format($graph["rate_fixed"]["amount"], $config_customers[$customer]["currency"]["precision"]);
						$text_part .= $config_customers[$customer]["currency"]["post"] . "\n";
						$text_part .= "    Total: \$" . number_format($total,2) . "\n\n";
	
						$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
						$csv_file .= "\"" . $graph["graph_title"] . "\",";
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Fixed\",";
						$csv_file .= "\"" . $graph["rate_fixed"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_fixed"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"\n";

					} elseif (isset($graph["rate_overage"])) {
						/* Committed rate */
						$total = calculate_rate($graph, "committed", $item["value_bits"]);
						$grand_total += round($total, 2);
						if ($item["value_bits"] > $graph["rate_overage"]["threshold"]) {
							$bits = $graph["rate_overage"]["threshold"];
						}else{
							$bits = $item["value_bits"];
						}
						$html_part .= "<tr>\n";
						$html_part .= "  <td>" . $item["type"] . "</td>\n";
						$html_part .= "  <td>" . $item["value_text"] . "</td>\n";
						$html_part .= "  <td>" . number_format(($bits / unit_multi($graph["rate_committed"]["unit"])), 2) . " " . $graph["rate_committed"]["unit"]  . "</td>\n";
						if (isset($graph["rate_committed"]["minimum"])) {
							if ($graph["rate_committed"]["amount"] <= 0) {
								$html_part .= "  <td>Committed</td>\n";
								$html_part .= "  <td>Fixed</td>\n";
							}else{
								$html_part .= "  <td>Committed <br><i>(Minimum: " . $config_customers[$customer]["currency"]["pre"];
								$html_part .= number_format($graph["rate_committed"]["minimum"], $config_customers[$customer]["currency"]["precision"]);
								$html_part .= $config_customers[$customer]["currency"]["post"] . ")</i></td>\n";
								$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"] . $graph["rate_committed"]["amount"];
								$html_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate_committed"]["unit"] . "</td>\n";
							}
						}else{
							$html_part .= "  <td>Committed</td>\n";
							$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"] . $graph["rate_committed"]["amount"];
							$html_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate_committed"]["unit"] . "</td>\n";
						}
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"];
						$html_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$html_part .= $config_customers[$customer]["currency"]["post"]. "</td>\n";
						$html_part .= "</tr>\n";

						$text_part .= "    Type: " . $item["type"] . "\n";
						$text_part .= "    Text: " . $item["value_text"] . "\n";
						$text_part .= "    Bits: " .  number_format(($bits / unit_multi($graph["rate_committed"]["unit"])), 2) . " " . $graph["rate_committed"]["unit"] . "\n";
						if (isset($graph["rate_committed"]["minimum"])) {
							if ($graph["rate_committed"]["amount"] <= 0) {
								$text_part .= "    Rate Type: Committed (Fixed)\n";
							}else{
								$text_part .= "    Rate Type: Committed (Minimum: " . $config_customers[$customer]["currency"]["pre"];
								$text_part .= number_format($graph["rate_committed"]["minimum"], $config_customers[$customer]["currency"]["precision"]);
								$text_part .= $config_customers[$customer]["currency"]["post"] . ")\n";
							}
						}else{
							$text_part .= "    Rate Type: Committed\n";
							$text_part .= "    Rate Amount: " . $config_customers[$customer]["currency"]["pre"] . $graph["rate_committed"]["amount"];
							$text_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate_committed"]["unit"] . "\n";
						}
						$text_part .= "    Total: " . $config_customers[$customer]["currency"]["pre"];
						$text_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$text_part .= $config_customers[$customer]["currency"]["post"] . "\n\n";
	
						$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
						$csv_file .= "\"" . $graph["graph_title"] . "\",";
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Committed\",";
						$csv_file .= "\"" . $graph["rate_committed"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_committed"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"\n";

						/* Overage rate */
						$total = calculate_rate($graph, "overage", $item["value_bits"]);			
						$grand_total += round($total, 2);
						if ($item["value_bits"] > $graph["rate_overage"]["threshold"]) {
							$bits = $item["value_bits"] - $graph["rate_overage"]["threshold"];
						}else{
							$bits = 0;
						}
						$html_part .= "<tr>\n";
						$html_part .= "  <td>" . $item["type"] . "</td>\n";
						$html_part .= "  <td>" . $item["value_text"] . "</td>\n";
						$html_part .= "  <td>" . number_format(($bits / unit_multi($graph["rate_overage"]["unit"])), 2) . " " . $graph["rate_overage"]["unit"] . "</td>\n";
						$html_part .= "  <td>Overage</td>\n";
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"] . $graph["rate_overage"]["amount"];
						$html_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate_overage"]["unit"] . "</td>\n";
						$html_part .= "  <td>" . $config_customers[$customer]["currency"]["pre"];
						$html_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$html_part .= $config_customers[$customer]["currency"]["post"] . "</td>\n";
						$html_part .= "</tr>\n";

						$text_part .= "    Type: " . $item["type"] . "\n";
						$text_part .= "    Text: " . $item["value_text"] . "\n";
						$text_part .= "    Bits: " .  number_format(($bits / unit_multi($graph["rate_overage"]["unit"])), 2) . " " . $graph["rate_overage"]["unit"] . "\n";
						$text_part .= "    Rate Type: Overage\n";
						$text_part .= "    Rate Amount: " . $config_customers[$customer]["currency"]["pre"] . $graph["rate_overage"]["amount"];
						$text_part .= $config_customers[$customer]["currency"]["post"] . " per " . $graph["rate_overage"]["unit"] . "\n";
						$text_part .= "    Total: " . $config_customers[$customer]["currency"]["pre"];
						$text_part .= number_format($total,$config_customers[$customer]["currency"]["precision"]);
						$text_part .= $config_customers[$customer]["currency"]["post"] . "\n";
	
						$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
						$csv_file .= "\"" . $graph["graph_title"] . "\",";
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Overage\",";
						$csv_file .= "\"" . $graph["rate_overage"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_overage"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"";

						/* Threshold notification */
						if (isset($item["threshold"])) {
							$html_part .= "<tr><td colspan=\"6\" align=\"center\"><b><i>Overage occurred at: " . date($date_format, $item["threshold"]["end"]) . "</td></tr>\n";
							$text_part .= "    *** Overage occurred at: " . date($date_format, $item["threshold"]["end"]) . "*** \n";
							$csv_file .= ",\"" . date($date_format, $item["threshold"]["end"]) . "\"";
						}else{
							$csv_file .= ",\"\"";
						}
						$text_part .= "\n";
						$csv_file .= "\n";
					}

				}
				$html_part .= "<tr><td colspan=\"5\" align=\"right\"><b>Total: </b></td><td>" . $config_customers[$customer]["currency"]["pre"];
				$html_part .= number_format($grand_total, $config_customers[$customer]["currency"]["precision"]) . $config_customers[$customer]["currency"]["post"] . "</td></tr>\n";
				$html_part .= "</table><br><br>\n";
				$text_part .= "  Graph Total: " . $config_customers[$customer]["currency"]["pre"] . number_format($grand_total, $config_customers[$customer]["currency"]["precision"]);
				$text_part .= $config_customers[$customer]["currency"]["post"]. "\n\n";
				$customer_total += round($grand_total, $config_customers[$customer]["currency"]["precision"]);
			}
			$html_part .= "<b>Customer Total: </b>" . $config_customers[$customer]["currency"]["pre"] . number_format($customer_total, $config_customers[$customer]["currency"]["precision"]);
			$html_part .= $config_customers[$customer]["currency"]["post"] . "<br><br>\n";
			$text_part .= "Customer Total: " . $config_customers[$customer]["currency"]["pre"] . number_format($customer_total, $config_customers[$customer]["currency"]["precision"]);
			$text_part .= $config_customers[$customer]["currency"]["post"] . "\n\n";
		}else{
			$html_part .= "<br><br><font color=\"red\" size=\"+3\">ERROR: NO GRAPHS DEFINED FOR THIS CUSTOMER!</font><br><br>\n";
			$text_part .= "\n\nERROR: NO GRAPHS DEFINED FOR THIS CUSTOMER!\n\n";
		}
		$html_part .= "</div>\n";
		$text_part .= "\n\n";
	}
	if (isset($config_globals["email"]["footer"])) {
		$html_part .= "<br>" . $config_globals["email"]["footer"] . "<br>\n";
		$text_part .= "\n\n" . $config_globals["email"]["footer"] . "\n\n";
	}


	$html_part .= "</body></html>\n";
	debug("  Emailing reports to email: " . $email["address"]);
	
	/* Attach CSV */
	if ($email["csv"] == 1) {
		if ($obj_mail->Attach($csv_file, $config_globals["email"]["csv_file_name"] . ".csv", "application/vnd.ms-excel") === false) {
			print "ERROR: Unable to attach csv file: " . $obj_mail->error() . "\n";
			$error = true;
		}
	}

	/* Send email */
	if ($email["html"] == 1) {
		if ($obj_mail->send( array( "text"=>$text_part, "html"=>$html_part ) ) === false) {
			print "ERROR: Unable to send mail to: " . $email["address"] . "\n";
			print "ERROR: " . $obj_mail->error() . "\n";
			$error = true;
		}
	}else{
		if ($obj_mail->send($text_part) === false) {
			print "ERROR: Unable to send mail to: " . $email["address"] . "\n";
			print "ERROR: " . $obj_mail->error() . "\n";
			$error = true;
		}
	}

	/* Kill mail object */
	$obj_mail->close();
	$obj_mail = FALSE;


}
if (! empty($images)) {
	debug("Removing temporary image files:");	
	foreach ($images as $image => $image_data) {
		debug("  " . $image);
		unlink($image);
	}
}


/* 
*****************************************************************************
* Process Threshold Emails 
*****************************************************************************
*/
if ((sizeof($threshold_emails) > 0) and ($config_globals["threshold_tracking"]["notification"] == 1)) {
	debug("Processing threshold email notifications");

	/* Process each customer */
	foreach ($threshold_emails as $customer_key => $emails) {

		debug("  Customer: " . $config_customers[$customer_key]["description"]);

		/* Determine if we have exceeded threshold and require a notification */
		$threshold_exceed = array();
		foreach ($config_customers[$customer_key]["graphs"] as $graph) {

			foreach ($graph["values"] as $graph_item_id => $data) {

				/* Setup notification tracking */
				if (! isset($config_track[ $config_customers[$customer_key]["description"] ]["notification"][ $graph["id"] ])) {
					$config_track[ $config_customers[$customer_key]["description"] ]["notification"][ $graph["id"] ] = 0;
				}

				/* Check that we can resend Nth percentile notifications */
				if (isset($data["threshold"])) {
					if ($data["threshold"]["type"] == "Nth") {
					
						$seconds = round( ($config_customers[$customer_key]["timeframe"]["threshold_end"] - $config_customers[$customer_key]["timeframe"]["threshold_start"]) / $notification_interval_divider, 0) - 3600;

						if ($current_time - $seconds > $config_track[ $config_customers[$customer_key]["description"] ]["notification"][ $graph["id"] ]) {
							$config_track[ $config_customers[$customer_key]["description"] ]["notification"][ $graph["id"] ] = 0;
						}
					}
				}

				/* Only send notification if we haven't before or are allowed to again */
				if ($config_track[ $config_customers[$customer_key]["description"] ]["notification"][ $graph["id" ] ] == 0) {
					if (isset($data["threshold"])) {
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["type_description"] = $data["type"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["type"] = $data["threshold"]["type"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["value"] = $data["threshold"]["value"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["end"] = $data["threshold"]["end"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["value_text"] = $data["value_text"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["threshold"] = $graph["rate_overage"]["threshold"];
						$threshold_exceed[ $graph["id"] ][$graph_item_id]["graph_title"] = $data["graph_title"];
					}
				}
			}
		}

		/* Send email notifications */
		if (sizeof($threshold_exceed) > 0) {

			debug("    Sending notification of overage");

			/* Create email object */
			$obj_mail = new Mailer(array( 
				"WordWrap"=>"132",
				"Type"=>$mailer_type,
				"SMTP_Host"=>$mailer_smtp_server,
				"SMTP_Port"=>$mailer_smtp_port,
				"SMTP_Username"=>$mailer_smtp_username,
				"SMTP_Password"=>$mailer_smtp_password,
				"CharSet"=>$TargetEncoding
				));

			/* Set email message properties */
			$obj_mail->header_set("From", $obj_mail->email_format($config_globals["threshold_tracking"]["title"] ,$config_globals["email"]["return_address"]));
			$obj_mail->header_set("Subject", $config_globals["threshold_tracking"]["subject"] . " - " . date($date_format));
			foreach ($emails as $address) {
				$obj_mail->header_set("To", $address);
			}
			$text_part = "";
			$html_part = "";

		
			/* Create email content */			
			$text_part .= $config_globals["threshold_tracking"]["title"] . "\n\n";
			$text_part .= "The following have exceeded committed rate limits:\n\n";

			$html_part .= "<font size=\"+1\"><b>" . $config_globals["threshold_tracking"]["title"] . "</b></font><br><br>";
			$html_part .= "The following have exceeded committed rate limit:<br><br>";

			foreach ($threshold_exceed as $graph_id => $graph) {
				foreach ($graph as $graph_item_id => $data) {

					$text_part .= "  " . $data["graph_title"] . "\n";
					$text_part .= "    Type: " . $data["type_description"] . "\n";
					$text_part .= "    Committed Rate: " . unit_display($data["threshold"]) . "\n";
					$text_part .= "    Occured: " . date($date_format, $data["end"]) . "\n";
					$text_part .= "    Value: " . unit_display($data["value"]) . "\n\n";

					$html_part .= "&nbsp;&nbsp;<b><i>" . $data["graph_title"] . "</i></b><br>\n";
					$html_part .= "&nbsp;&nbsp;&nbsp;&nbsp;<b>Type:</b> " . $data["type_description"] . "<br>\n";
					$html_part .= "&nbsp;&nbsp;&nbsp;&nbsp;<b>Committed Rate</b>: " . unit_display($data["threshold"]) . "<br>\n";
					$html_part .= "&nbsp;&nbsp;&nbsp;&nbsp;<b>Occured:</b> " . date($date_format, $data["end"]) . "<br>\n";
					$html_part .= "&nbsp;&nbsp;&nbsp;&nbsp;<b>Value:</b> " . unit_display($data["value"]) . "<br><br>\n";

					if ($data["type"] == "Nth") {
						$text_part .= "  *** If you continue at your current rate of usage, you will exceed your committed rate at time of billing. ***\n\n";
						$html_part .= "&nbsp;&nbsp;<b><i><font color=\"red\">If you continue at your current rate of usage, you will exceed your committed rate at time of billing.</font></i></b>\n\n";
					}

				}
			}


			/* Send email */
			$error = FALSE;
			if ($email["html"] == 1) {
				if ($obj_mail->send( array( "text"=>$text_part, "html"=>$html_part ) ) === false) {
					print "ERROR: Unable to send mail to: " . $email["address"] . "\n";
					print "ERROR: " . $obj_mail->error() . "\n";
					$error = TRUE;
				}
			}else{
				if ($obj_mail->send($text_part) === false) {
					print "ERROR: Unable to send mail to: " . $email["address"] . "\n";
					print "ERROR: " . $obj_mail->error() . "\n";
					$error = TRUE;
				}
			}

			/* Kill mail object */
			$obj_mail->close();
			$obj_mail = FALSE;

			/* Update notification count in track file */
			if ($error == FALSE) {
				foreach ($threshold_exceed as $graph_id => $graph) {
					$config_track[ $config_customers[$customer_key]["description"] ]["notification"][$graph_id] = $current_time;
				}
			}

		}else{
			debug("    No notification of overage");
		}
	}

}


/* 
*****************************************************************************
* Process file exports 
*****************************************************************************
*/
if (sizeof($file_index) > 0) {
	debug("Processing export files");
} else {
	debug("No export files to process");
}
$error = false;
foreach ($file_index as $file) {

	debug("  File: " . $file["path"]);
	debug("  Format: " . $file["format"]);
	debug("  Process: " . $file["process"]);
	debug("  Customers:");

	/* File header */
	$csv_file = "\"CustomerDescription\",\"CustomerExternalId\",\"BillingPeriodStart\",\"BillingPeriodEnd\",\"GraphId\",\"GraphTitle\",\"GraphExternalId\",";
	$csv_file .= "\"GraphItemId\",\"Type\",\"Interval\",\"Every\",\"RateType\",\"RateAmount\",\"RateUnit\",\"Bits\",\"Total\",\"OverageOccurred\"\n";

	/* Process customer data */
	foreach ($file["customers"] as $customer) {

		debug("    " . $config_customers[$customer]["description"]);

		/* Locate Billing Timeframe - Defined per customer or as a default */
		if (isset($config_customers[$customer]["billing_timeframe"])) {
			$billing_timeframe = $config_customers[$customer]["billing_timeframe"];
		}else{
			$billing_timeframe = $config_defaults["billing_timeframe"];
		}

		/* Process graph data */
		if (sizeof($config_customers[$customer]["graphs"]) > 0) {
			foreach ($config_customers[$customer]["graphs"] as $graph_key => $graph) {
				foreach ($graph["values"] as $item_key => $item) {

					$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
					if (isset($config_customers[$customer]["external_id"])) {
						$csv_file .= "\"" . $config_customers[$customer]["external_id"] . "\",";
					}else{
						$csv_file .= "\"\",";
					}
					$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
					$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
					$csv_file .= "\"" . $graph["id"] . "\",";
					$csv_file .= "\"" . $graph["graph_title"] . "\",";
					if (isset($graph["external_id"])) {
						$csv_file .= "\"" . $graph["external_id"] . "\",";
					}else{
						$csv_file .= "\"\",";
					}
					$csv_file .= "\"" . $item_key . "\",";

					if (isset($graph["rate_overage"])) {
						/* Committed rate */
						$total = calculate_rate($graph, "committed", $item["value_bits"]);
						if ($item["value_bits"] > $graph["rate_overage"]["threshold"]) {
							$bits = $graph["rate_overage"]["threshold"];
						}else{
							$bits = $item["value_bits"];
						}
	
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Committed\",";
						$csv_file .= "\"" . $graph["rate_committed"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_committed"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\",";

						/* Threshold notification */
						if (isset($item["threshold"])) {
							$csv_file .= "\"" . date($date_format, $item["threshold"]["end"]) . "\"";
						}else{
							$csv_file .= "\"\"";
						}
						$csv_file .= "\n";

						/* Overage rate */
						$total = calculate_rate($graph, "overage", $item["value_bits"]);			
						if ($item["value_bits"] > $graph["rate_overage"]["threshold"]) {
							$bits = $item["value_bits"] - $graph["rate_overage"]["threshold"];
						}else{
							$bits = 0;
						}
						$csv_file .= "\"" . $config_customers[$customer]["description"] . "\",";
						if (isset($config_customers[$customer]["external_id"])) {
							$csv_file .= "\"" . $config_customers[$customer]["external_id"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["start_formatted"] . "\",";
						$csv_file .= "\"" . $config_customers[$customer]["timeframe"]["end_formatted"] . "\",";
						$csv_file .= "\"" . $graph["id"] . "\",";
						$csv_file .= "\"" . $graph["graph_title"] . "\",";
						if (isset($graph["external_id"])) {
							$csv_file .= "\"" . $graph["external_id"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"" . $item_key . "\",";
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Overage\",";
						$csv_file .= "\"" . $graph["rate_overage"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_overage"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"";
	
					} elseif (isset($graph["rate"])) {
						/* Regular rate */
						$total = calculate_rate($graph, "regular", $item["value_bits"]);			
						$bits = $item["value_bits"];
	
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Regular\",";
						$csv_file .= "\"" . $graph["rate"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"";
					} elseif (isset($graph["rate_fixed"])) {
						/* Fixed rate */
						$total = calculate_rate($graph, "fixed", $item["value_bits"]);			
						$bits = $item["value_bits"];
	
						$csv_file .= "\"" . $item["type"] . "\",";
						$csv_file .= "\"" . $billing_timeframe["type"] . "\",";
						if (isset($billing_timeframe["every"])) {
							$csv_file .= "\"" . $billing_timeframe["every"] . "\",";
						}else{
							$csv_file .= "\"\",";
						}
						$csv_file .= "\"Fixed\",";
						$csv_file .= "\"" . $graph["rate_fixed"]["amount"] . "\",";
						$csv_file .= "\"" . $graph["rate_fixed"]["unit"] . "\",";
						$csv_file .= "\"" . $bits . "\",";
						$csv_file .= "\"" . $total . "\"";

					}

					/* Threshold notification */
					if (isset($item["threshold"])) {
						$csv_file .= ",\"" . date($date_format, $item["threshold"]["end"]) . "\"";
					}else{
						$csv_file .= ",\"\"";
					}
					$csv_file .= "\n";

				}
			}
		}
	}

	/* Open file for writting */
	if (!$export_FH = fopen($file["path"], "w")) {
		print "ERROR: Unable to open file for writing: " . $file["path"] . "\n";
		next;
	}

	/* Write file */
	if ($file["format"] == "csv") {
		fwrite($export_FH, $csv_file);
	}else{
		fwrite($export_FH, $xml_file);
	}
	
	/* Close file */
	fclose($export_FH);

	/* Run external process program on export file */
	if (! empty($config_globals["export_file_processor"])) {
		if ($file["process"] == 1) {
			debug("  Running export file processor:");
			debug("    Program: " . $config_globals["export_file_processor"]);
			debug("    Command Line Arguments: '" . $file["path"] . "' '" . $file["format"] . "'");
			debug("    Current Working Directory: '" . dirname(__FILE__) . "'");
			if (chdir(dirname(__FILE__))) {
				if (file_exists($config_globals["export_file_processor"])) {
					if (is_executable($config_globals["export_file_processor"])) {
						$command = $config_globals["export_file_processor"] . " ";
						$command .= "'" . $file["path"] . "' ";
						$command .= "'" . $file["format"] . "'";
						exec($command, $output, $return_value);
						if ($return_value != 0) {
							print "ERROR: Export file processor exited with an error\n";
							print "Program Output:\n";
							foreach ($output as $line) {
								print $line . "\n";
							}
						}
					}else{
						print "ERROR: Export file processor '" . $config_globals["export_file_processor"] . "' is not executable.\n";
	
					}
				}else{
					print "ERROR: Export file processor '" . $config_globals["export_file_processor"] . "' does not exist.\n";
				}
			}else{
				print "ERROR: Unable to change current working directory to script location\n";
			}
		}
	}

}


/*
*****************************************************************************
* Process track updates 
*****************************************************************************
*/
if ((! $cmd_track_no_write) && (! $error)) {
	debug("Updating track file");

	/* Update track array */
	foreach ($config_customers as $customer_key => $customer) {
		debug("  Customer: " . $customer["description"]);
		if (isset($config_track[$customer["description"]]["last_run"])) {
			debug("    Last Run Time: " . date($date_format, $config_track[$customer["description"]]["last_run"]));
		}
		if (isset($config_customers[$customer_key]["timeframe"])) {
			if ($config_customers[$customer_key]["timeframe"]["end"] !=0) {
				debug("    New Last Run Time: " .  date($date_format, $config_customers[$customer_key]["timeframe"]["end"] + 1));
				$config_track[$customer["description"]]["last_run"] = $config_customers[$customer_key]["timeframe"]["end"] + 1;

				/* reset notifications when we have processed billing */
				if (isset($config_track[$customer["description"]]["notification"])) {
					foreach ($config_track[$customer["description"]]["notification"] as $graph_id => $value) {
						$config_track[$customer["description"]]["notification"][$graph_id] = 0;
					}
				}
			}
		}else{
			debug("    No billing ran for customer, update skipped");
		}
	}	

	/* Clean out old threshold tracking data */
	debug("Removing old threshold data from track file");
	foreach ($config_track as $customer_description => $customer) {
		debug("  Customer: " . $customer_description);
		foreach ($customer as $graph_id => $graph) {
			if (($graph_id == "last_run") || ($graph_id == "notification")) continue;
			foreach ($graph as $graph_item_id => $graph_item) {
				$timestamps = array_keys($graph_item);
				if (count($timestamps) > 2) {
					ksort($timestamps, SORT_NUMERIC);
					for ($i = 0; $i < count($timestamps) - 2; $i++) {
						debug("    Graph: " . $graph_id . " Graph Item: " . $graph_item_id . " - " . $timestamps[$i]);
						unset($config_track[$customer_description][$graph_id][$graph_item_id][$timestamps[$i]]);
					}
				}
			}
		}
	}

	/* Write the track file */
	if (! write_track($config_track, $cmd_track)) {
		print "ERROR: Unable to write track file";
	}
}
if ($error) {
	print "Error encountered during emailing, track file update skipped\n";
}

/* 
*****************************************************************************
* Clean up session and exit 
*****************************************************************************
*/
session_unset();

debug("Done at " . date($date_format));
exit;

?>
