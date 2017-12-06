<?php


///	This file defines the class transaction_object and related functions
//	@programmer Craig Millis
//	Which files call this file or functions: csv_parser.php
//  The class is used by a parser that reads a csv file and writes it to an array.

class transaction_object {
	protected $type 	  	= "";
	protected $header 		= array();
	protected $data 	  	= array();
  	protected $reserved 	= array('LIMIT', '1099');
  
	public function __construct($type, $header=array(), $data=array()) {
		if (is_string($type) && !empty($type)) {
			$this->type 	= $type;
		}else {
			throw new Exception(" Invalid Transaction Type");
		}
		$this->header = $header;
    $this->data   = $data;
	}

	public function add_header($header) {
    if (is_array($header) && !empty($header)) {
			// Check each element of header and surround numbered column names with back-ticks and rename column name that starts with !
			for ($i = 0; $i < count($header); $i++) {
				if (is_numeric($header[$i]) || array_search($header[$i], $this->reserved) !== FALSE) {
					$header[$i] = "`" . $header[$i] . "`";
				}
				if (is_string($header[$i])) {
					if (substr($header[$i], 0, 1) == "!") {
						$header[$i] = "trns_cat";
					}
        }else {
					$header[0][0] = "trns_cat";
					$header[1][0] = "trns_cat";
				//	$header[2][0] = "trns_cat";
        }
			}
			// trim off trailing empties
			for ($i=count($header); empty($header[$i]); $i--) {
				unset($header[$i]);
      }
			$this->header 	= $header;
		}
	}

	public function add_list($data) {
		if (is_array($data) && !empty($data)) {
			$this->data[]	= $data;
		}else {
			throw new Exception("Invalid Data for Transaction");
		}
	}

	public function get_data() {
		return $this->data;
	}

	public function get_header() {
		return $this->header;
	}

	public function merge_header($array) {
		$rtn_array 	= array();
		$count 		= 0;
		
		foreach ($this->header as $key) {
			$rtn_array[$key] = $array[$count];
			$count++;
		}

		return $rtn_array;
	}
}

?>
