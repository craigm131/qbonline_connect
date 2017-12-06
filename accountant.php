<?php

/*
*	This file defines the class accountant and the function insert_tables.
*	@programmer Craig Millis
*   Which files call this file: qb_master.php
*   Debugging notes:
*   How this file will be used: this file takes a client's home transactions, that are listed in a csv file, and inserts them into a MySQL database.
*/

require_once('csv_parser.php');

class accountant {

	protected $transaction_list;
	protected $client;
	protected $db;

	public function __get($var_name) {
		if(isset($this->$var_name)) {
			return $this->$var_name;
		}
	}

	/* info on this function */
	public function __construct($path, $db, $client) {
		// input validation
		if (empty($path) || empty($client)){
			// throw an error
			throw new Exception("Invalid input error: not array, missing filename, or missing client name.");
		}

		// create parser object
		$parser = new home_csv_parser();

		// read in 
		$this->transaction_list = $parser->parse_file($path);
		$this->client 		  	= $client;
		$this->db 			    = $db;
		$this->filename 		= "'" . basename($path) . "'";
		$this->path  			= dirname($path);
	}

	//this function will use a default database unless another db is passed into it
	public function insert_tables($mysqli = NULL) {
		$mysqli = $this->db;

		date_default_timezone_set('UTC');
		
		//Begin transaction
		$mysqli->autocommit(FALSE);

		//Insert data into homefiles table
		$sql_homefiles = "INSERT INTO homefiles (file_name)
		VALUES (" . $this->filename .")";
		$error = "";

		if ($mysqli->query($sql_homefiles) === TRUE) {
			$homefile_id = $mysqli->insert_id;
		}else {
			$error .= "\r\n" . $mysqli->error;
			throw new Exception($mysqli->error);
		}
		$foreign_key = $mysqli->insert_id;

    	//New code to accommodate data files that may contain only one type of transaction (eg only invoices)
		foreach ($this->transaction_list as $transaction_type){ //transaction_list is an array that contains objects.  Loop through each type of transaction in file (eg vendor, customer, )
			$customer_ref 	= 0;
			$vendor_ref		  = 0;
			$txn_id			    = 0;
			$entry_id		    = 1;
			$entry_line		  = 0;
			$table_headers	= $transaction_type->get_header(); //returns one header for cust and vend; returns three headers, where [0] is for trns, [1] is for spl, and [2] is for endtrns but not used
      		foreach($transaction_type->get_data() as $transaction){//$transaction_type is an object of class home_transaction_object.  $transaction_type->get_data() returns an array
      			if ($transaction[0] == 'CUST' || $transaction[0] == 'VEND'){
					// record sanitized data in the $table_row array (sanitized by real_escape_string)
      				$count = 0;
      				$table_row = array();
      				foreach($table_headers as $header_key=>$header_value){
      					if( isset($transaction[$header_key]) ){
      						$table_row[$header_key] = $mysqli->real_escape_string($transaction[$header_key]);
      					}else{
      						$table_row[$header_key] = "";
      					}
      					$count++;
      				} 
	          		// combine the header array with the data array, slice off the first column (VEND), filter out nulls, and append foreign key
      				$table_combined = array_combine($transaction_type->get_header(), $table_row);
      				$table_combined = array_filter($table_combined, 'strlen');

					//add single quotes to the data portion of the combined array so that SQL INSERT works
      				foreach ($table_combined as $key=>$value) {
      					$table_combined[$key] 	= "'" . $table_combined[$key] . "'";
      				}

      				$table_cols 	= implode(", ", array_keys($table_combined));
      				$table_row 		= implode(", ", array_values($table_combined));
      				$table_cols 	.= ", qb_val, homefile_id";
      				$table_row		.= ", 'PENDING', " . $foreign_key;

					// put row into db
      				if ($transaction[0] == 'VEND'){
						//echo "\nrecord vendor";
      					$sql = "INSERT INTO vendors ($table_cols) VALUES ($table_row)";
      				}elseif ($transaction[0] == 'CUST'){
						//echo "\nrecord customer";
      					$sql = "INSERT INTO customers ($table_cols) VALUES ($table_row)";
      				}
      				if ($mysqli->query($sql) === TRUE){
						//echo "\nROW ENTERED";
      				}else{
      					$error .= "\r\n" . $mysqli->error;
						//throw new Exception($mysqli->error);
      				}
      			}elseif($transaction[0] == "MALFORMED"){
      				$count = 0;
      				$table_row = array();
      				foreach ($transaction as $key=>$value){
						// record sanitized data in the $table_row array (sanitized by real_escape_string)
      					if ($count < count($transaction_type->get_header())) {
      						$table_row[$key] = $mysqli->real_escape_string($value);
      						$count++;
      					}else{
      						if (!empty($transaction[$key])) {
      							echo "Notice: ".__FILE__." $key => $value\n";
      						} 
      					}
      				}
					// combine the header array with the data array, slice off the first column, filter out nulls, and append foreign key
      				$table_combined = array_combine($transaction_type->get_header(), $table_row);
      				$table_combined = array_filter($table_combined, 'strlen');

					//add single quotes to the data portion of the combined array so that SQL INSERT works
      				foreach ($table_combined as $key=>$value) {
      					$table_combined[$key] 	= "'" . $table_combined[$key] . "'";
      				}

      				$table_cols 	= implode(", ", array_keys($table_combined));
      				$table_row 		= implode(", ", array_values($table_combined));
      				$table_cols 	.= ", homefile_id";
      				$table_row		.= ", " . $foreign_key;

					// put row into db
      				$sql = "INSERT INTO malformed ($table_cols) VALUES ($table_row)";
      				if ($mysqli->query($sql) === TRUE){
						//echo "\nROW ENTERED";
      				}else{
      					$error .= "\r\n" . $mysqli->error;
						//throw new Exception($mysqli->error);
      				}
      			}else{
					// Insert data into acctg, trns, and spl tables; prepare values for each pass
					//$table_headers	= $transaction_type->get_header(); //returns three headers, where [0] is for trns, [1] is for spl, and [2] is for endtrns but not used
      				foreach($transaction as $acctg_transaction){
      					if ($acctg_transaction[0] == "ENDTRNS") {
      						$entry_id++;
      						$entry_line = 0;
      					}else{
      						$entry_line++;
      						if ($entry_line == 1){
                				// Insert data into acctg table
                				//echo "\nacctg transaction " . $entry_id;
      							$sql_acctg = "INSERT INTO acctg (qb_val, homefile_id, entry_id) VALUES ('PENDING', $foreign_key, $entry_id)";
      							if ($mysqli->query($sql_acctg) === TRUE){
                  				//echo "\nacctg ROW ENTERED";
      							}else{
      								$error .= "\r\n" . $mysqli->error;
      							}
      							$acctg_foreign_key = $mysqli->insert_id;
      						}
      						if ($acctg_transaction[0] == "TRNS"){
      							$pick_header 		= 0;
      							$append_table_col 	= ", entry_id, entry_line, customer_ref, vendor_ref, txn_id, acctg_id";
      							$append_table_row	= ", " . $entry_id . ", " . $entry_line . ", " . $customer_ref . ", " . $vendor_ref . ", " . $txn_id . ", ". $acctg_foreign_key; 
      						}
      						if ($acctg_transaction[0] == "SPL"){
      							$pick_header 		= 1;
      							$append_table_col 	= ", entry_id, entry_line, acctg_id";
      							$append_table_row	= ", " . $entry_id . ", " . $entry_line . ", ". $acctg_foreign_key; 
      						}

      						$table_row = array();
      						foreach($table_headers[$pick_header] as $k=>$v){
      							if (isset($acctg_transaction[$k])){
      								$table_row[$v] = $mysqli->real_escape_string($acctg_transaction[$k]);
      							}else{
      								$table_row[$v] = NULL;
      							}
      						}

      						$table_row = array_filter($table_row, 'strlen');

              				//add single quotes to the data portion of the combined array and convert date so that SQL INSERT works
      						foreach ($table_row as $key=>$value) {
      							if (in_array($key, array('DATE','DUEDATE','SHIPDATE'))){
      								$value = date("Y-m-d", strtotime($value));
      							}
      							$table_row[$key] 	= "'" . $value . "'";
      						}

      						$table_col_str 		= implode(", ", array_keys($table_row));
      						$table_row_str 		= implode(", ", array_values($table_row));
      						$table_col_str 		.= $append_table_col;
      						$table_row_str		.= $append_table_row;

              				// put row into db
      						if ($acctg_transaction[0] == "TRNS"){
      							$sql_trns = "INSERT INTO trns ($table_col_str) VALUES ($table_row_str)";
      							if ($mysqli->query($sql_trns) === TRUE){
                  					//echo "\ntrns ROW ENTERED";
      							}else{
      								$error .= "\r\n" . $mysqli->error;
      							}
      						}
      						if ($acctg_transaction[0] == "SPL"){
      							$sql_spl = "INSERT INTO spl ($table_col_str) VALUES ($table_row_str)";
      							if ($mysqli->query($sql_spl) === TRUE){
                  					//echo "\nspl ROW ENTERED";
      							}else{
      								$error .= "\r\n" . $mysqli->error;
      							}
      						}
      					}
      				}
      			}
      		}
      	}

      	if ($error == ""){
      		if (!$mysqli->commit()){
      			echo "\n".basename(__FILE__).": datafile NOT copied to MySQL, failed to commit";
      			if($mysqli->rollback()){
      				echo "\n".basename(__FILE__).": transaction rolled back";
					$mysqli->autocommit(TRUE);//@TODO: remove once servers upgraded to php 5.6
				}else{
					echo "\n".basename(__FILE__).": transaction failed to roll back";
				}
				echo "\n".basename(__FILE__).": ". $this->filename;
				return;
			}else{
				echo "\n".basename(__FILE__).": datafile copied to MySQL";
				echo "\n".basename(__FILE__).": " . $this->filename;
				$mysqli->autocommit(TRUE);//@TODO: remove once servers upgraded to php 5.6
				return $homefile_id;
			}
		}else{      
			echo "\n$error";  
			echo "\n".basename(__FILE__).": datafile NOT copied to MySQL";
			if($mysqli->rollback()){
				echo "\n".basename(__FILE__).": transaction rolled back";
				$mysqli->autocommit(TRUE);//@TODO: remove once servers upgraded to php 5.6
			}else{
				echo "\n".basename(__FILE__).": transaction failed to roll back";
			}
			return;
		}
		return;
	}

  	//This function is used to write the data array to a text file for debugging
	function printData($data, $path){
		$filepath = $path."/var_dump.txt";

		ob_start();
		var_dump($data);
		$result           = ob_get_clean();
		$i                = 0;
		$length           = strlen($result);
		$formatted_result = "";

		while($i <= $length){
			$pip = substr($result, $i, 1);
			switch($pip){
				case "{":
				$formatted_result .= $pip."\r\n\t\t";
				break;
				case "[":
				$formatted_result .= "\r\n".$pip;
				break;
				default:
				$formatted_result .= $pip;
			}
			$i++;
		}
		file_put_contents($filepath, $formatted_result);
	}
}

?>
