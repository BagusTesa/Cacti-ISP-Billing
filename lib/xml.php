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

/* XML Parser */
class xml2Array {
	  
	var $arrOutput = array();
	var $resParser;
	var $strXmlData;
	
	function parse($strInputXML) {
		global $config, $XMLEncoding, $TargetEncoding;

		/* figure out the encoding of the XML file, php bugs */
		$match = array();
		if (preg_match("/<?xml.*encoding=['\"](.*?)['\"].*?>/m", $strInputXML, $match)) {
			$XMLEncoding = strtoupper($match[1]);
			debug("XML encoding read from file: " . $XMLEncoding);
		}else{
			if ($config["cacti_server_os"] == "WIN") {
				$XMLEncoding = "ISO-8859-1";
			}else{
				$XMLEncoding = "UTF-8";
			}
			debug("No XML encoding field defined encoding defaulting to " . $XMLEncoding);
		}

		/* setup the xml parse object with proper encoding */
		$TargetEncoding = "UTF-8";
		if (($XMLEncoding == "UTF-8") || ($XMLEncoding == "US-ASCII") || ($XMLEncoding == "ISO-8859-1")) {
			debug("XML parsing with default PHP handling");
		}else{
			if (function_exists("mb_convert_encoding")) {
				debug("XML parsing with multibyte encoding functions");
				debug("XML parsing multibyte detected encoding: " . mb_detect_encoding($strInputXML, "auto"));
				$fixed_strInputXML = @mb_convert_encoding($strInputXML, "UTF-8", $XMLEncoding);
				$strInputXML = str_replace($match[0], "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>", $fixed_strInputXML);
				$fixed_strInputXML = "";
				$XMLEncoding = "UTF-8";
			}else{
				if ($config["cacti_server_os"] == "WIN") {
					$XMLEncoding = "ISO-8859-1";
					$TargetEncoding = "ISO-8859-1";
				}else{
					$XMLEncoding = "UTF-8";
				}
				debug("XML parsing with multibye encoding functions failed.  MBString module not installed, defaulting to \"" . $XMLEncoding . "\" encoding.");
			}
		}
		debug("XML parsing internal encoding set to \"" . $TargetEncoding . "\".");
		$this->resParser = xml_parser_create($XMLEncoding);
		xml_parser_set_option($this->resParser, XML_OPTION_TARGET_ENCODING, $TargetEncoding);
		xml_set_object($this->resParser,$this);

		/* setup parsing functions */
		xml_set_element_handler($this->resParser, "tagOpen", "tagClosed");
		xml_parser_set_option($this->resParser, XML_OPTION_SKIP_WHITE, 1);
		xml_parser_set_option($this->resParser, XML_OPTION_CASE_FOLDING, 0);
		xml_set_character_data_handler($this->resParser, "tagData");

		$this->strXmlData = xml_parse($this->resParser,$strInputXML );
		if(!$this->strXmlData) {
			print sprintf("ERROR: XML %s at line %d\n",
			xml_error_string(xml_get_error_code($this->resParser)),
			xml_get_current_line_number($this->resParser));
			return FALSE;
		}

		xml_parser_free($this->resParser);

		return $this->arrOutput;
	}

	function tagOpen($parser, $name, $attrs) {
		$tag=array("name"=>$name,"attrs"=>$attrs);
		array_push($this->arrOutput,$tag);
	}

	function tagData($parser, $tagData) {
		if(strlen(trim($tagData)) != 0) {
			if(isset($this->arrOutput[count($this->arrOutput)-1]['tagData'])) {
				$this->arrOutput[count($this->arrOutput)-1]['tagData'] .= $tagData;
			}else{
				$this->arrOutput[count($this->arrOutput)-1]['tagData'] = $tagData;
			}
		}
	}

	function tagClosed($parser, $name) {
		$this->arrOutput[count($this->arrOutput)-2]['children'][] = $this->arrOutput[count($this->arrOutput)-1];
		array_pop($this->arrOutput);
	}

}

?>
