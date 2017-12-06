<?php

///	This file defines the class that will read a data file, returning an array, and uses headers defined in the class.
//	@programmer Craig Millis
//  This file is called by accountant.php

require_once("transaction_object.php");

class csv_parser {

  //  CUST          obj     object that contains customer transactions
  //  VEND          obj     object that contains vendor transactions
  //  TRNS          obj     object that contains multi-line transactions (e.g. invoice, bill, etc)
  //  malformed     obj     object that contains malformed records
  //  data          array   array that represents one line read from the data file
  //  prior_record  array   array that represents the prior line read from the data file.  Used to find malformed lines in multi-line transacitons.
  //  lines         array   array that temporarly holds non-malformed data before being pushed to the rtn_data array
  //  flag_multi    bool    Boolean to keep track of when the fgetcsv point is within a multi-line transaction. It will be true if the data row was TRNS until the data row is ENDTRNS
  //  rtn_data      array   The array of objects that is returned after reading an entire data file. It will contain one or all of the objects CUST, VEND, TRNS, or malformed. 
  //  header_type   array   The keys in this array are what should appear as data[0]
  //  trnstype      array   Multi-line transactions will only show these values in data[1].
  //  default_headers array The headers that correspond for each line, VEND, CUST, TRNS, SPL, ENDTRNS, or MALFORMED.
  private $CUST;
  private $VEND;
  private $TRNS;
  private $malformed;
  private $data;
  private $prior_record;
  private $lines;
  private $flag_multi;
  private $rtn_data;
  private $header_type;
  private $trnstype;
  private $default_headers;

  function __construct(){
    $this->CUST             = NULL;
    $this->VEND             = NULL;
    $this->TRNS             = NULL;
    $this->malformed        = NULL;
    $this->flag_multi       = FALSE;
    $this->rtn_data         = array();
    $this->header_type 	    = array("VEND"=>"SINGLE", "CUST"=>"SINGLE", "TRNS"=>"MULTI", "SPL"=>"MULTI", "ENDTRNS"=>"MULTI");
    $this->trnstype         = array('CHECK', 'GENERAL JOURNAL', 'PAYMENT', 'BILL', 'BILL REFUND', 'INVOICE', 'CREDIT MEMO');
    $this->default_headers  = array(
      "VEND"  =>array(
        "!VEND", "ACCOUNT_NO", "PRINTAS", "ADDR1", "ADDR2", "ADDR3", "ADDR4", "ADDR5", "VTYPE", "CONT1", "CONT2", "PHONE1", "PHONE2", "FAXNUM",
        "EMAIL", "NOTE", "TAXID", "LIMIT", "TERMS", "SALUTATION", "COMPANYNAME", "FIRSTNAME", "MIDINIT", "LASTNAME", "1099", "HIDDEN", "DELCOUNT"
      ),
      "CUST"  =>array(
        "!CUST", "ACCOUNT_NO", "BADDR1", "BADDR2", "BADDR3", "BADDR4", "BADDR5", "SADDR1", "SADDR2", "SADDR3", "SADDR4", "SADDR5", "PHONE1", "PHONE2",
        "FAXNUM", "EMAIL", "NOTE", "CONT1", "CONT2", "CTYPE", "TERMS", "TAXABLE", "SALESTAXCODE", "LIMIT", "REP", "SALUTATION", "COMPANYNAME",
        "FIRSTNAME", "MIDINIT", "LASTNAME", "JOBSTATUS", "HIDDEN", "DELCOUNT"
      ),
      "TRNS"  =>array(
        "!TRNS", "TRNSTYPE", "DATE", "ACCNT", "NAME", "CLASS", "AMOUNT", "DOCNUM", "MEMO", "CLEAR", "TOPRINT", "PAYMETH", "DUEDATE", "TERMS",
        "ADDR1", "ADDR2", "ADDR3", "PAID", "SHIPDATE", "PONUM", "INVTITLE", "INVMEMO", "BATCH"
      ),
      "SPL"   =>array(
        "!SPL", "TRNSTYPE", "DATE", "ACCNT", "NAME", "CLASS", "AMOUNT", "DOCNUM", "MEMO", "CLEAR", "QNTY", "PRICE", "INVITEM"
      ),
      "ENDTRNS" =>array(
        "!ENDTRNS"
      ),
      "MALFORMED" =>array(
        "!MALFORMED", "value", "note"
      )
    );
  }

  ///This function reads a tab-delimited file, checking the first element of each line for "VEND", "CUST", or "TRNS".  Depending on that element
  //it will assign a header that's defined in the class.  
	public function parse_file($filename) {
		if (($handle = fopen($filename, "r")) !== FALSE) {
      $i                = 0;
      $n                = 0;
      $delimiter        = "\t";

			//Read file
      while(($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $i++;
        //Ignore records that start with !
        if ( substr($data[0], 0, 1) !== "!" ){
          if($data[0] == "TRNS"){
            $this->flag_multi = TRUE;
            $n = $i;
          }
          //Check if the record is malformed
          if ( $this->is_malformed($data) == TRUE){
            $this->write_malformed($data, $i, $n);
          }else{
            if($this->header_type[$data[0]] == "MULTI"){
              //A multi-line record, where $data[0] will be either TRNS, SPL, or ENDTRNS.
              $this->write_multi($data);
            }else{
              //A single-line record, where $data[0] will be either VEND or CUST.  
              $this->write_single($data);
            }
          }
        }
        //loop
        $this->prior_record = $data;
      }
      //End of loop.  Add the four objects to an array
      $items = array($this->malformed, $this->CUST, $this->VEND, $this->TRNS);
      foreach($items as $item){
        if($item !== NULL){
          array_push($this->rtn_data, $item);
        }  
      }
      return $this->rtn_data;
    }
  }

  ///This function records multiple-line transactions in the lines array until the last line, at which time it adds the lines array to the TRNS object.
  private function write_multi($data){
    $this->flag_multi = TRUE;
    if($this->TRNS !== NULL){
      if($data[0] == "ENDTRNS"){
        //add array of data to transaction object
        $this->lines[] = $data;
        $this->TRNS->add_list($this->lines);
        $this->lines = array();
        $this->flag_multi = FALSE;
      }else{
        //add row of data to parser object until the multi line transaction is complete.
        $this->lines[] = $data;
      }
    }else{
      $this->TRNS = new transaction_object("TRNS");
      $this->TRNS->add_header(array($this->default_headers["TRNS"], $this->default_headers["SPL"]));
      $this->lines[] = $data;
    }
  }

  ///This function records single line transactions (CUST or VEND), adding each one to the object.
  private function write_single($data){
    if($this->$data[0] !== NULL){
      //add row of data to transaction object
      $this->$data[0]->add_list($data);
    }else{
      $this->$data[0] = new transaction_object($data[0]);
      $this->$data[0]->add_header($this->default_headers[$data[0]]);
      $this->$data[0]->add_list($data);
    }
  }

  ///This function records malformed lines - those that match the criteria shown in the is_malformed function
  private function write_malformed($data, $i, $n){
    if($this->flag_multi == TRUE){
      $value = "See note";
      $note  = $this->malformed_msg.": rows ".$n." to ".$i." not recorded";
      $this->malformed_msg = NULL;
      //Clear out any multi line data
      $this->flag_multi = FALSE;
      $this->lines = array();
    }else{ 
      $value = "";
      foreach($data as $element){
        $value .= $element;
      }
      $value  = substr($value, 0, 399);//limit the string length to what the Mysql field can accept
      $note   = $this->malformed_msg.": row ".$i." not recorded";
    }  
    $malformed_data = array("MALFORMED", $value, $note);          
    if($this->malformed !== NULL){
      //add row of data (lines array) to transaction object
      $this->malformed->add_list($malformed_data);
    }else{
      $this->malformed = new transaction_object("MALFORMED");
      $this->malformed->add_header($this->default_headers["MALFORMED"]);
      $this->malformed->add_list($malformed_data);
    } 
  }

  ///This function identifies any lines that cannot be written to MySQL (and then onto Quickbooks Online)
  private function is_malformed($data) {

    if( !in_array($data[0], array_keys($this->header_type)) ){
      $this->malformed_msg = "The transaction type is not known";
      return TRUE;
    }

    if( in_array($data[0], array("TRNS", "SPL")) AND !in_array($data[1], $this->trnstype) ){
      $this->malformed_msg = "The transaction is multi-line, but the sub-type (e.g. INVOICE, BILL, etc) is not known";
      return TRUE;
    }

    if( $this->flag_multi == FALSE AND ($data[0] == "SPL" || $data[0] == "ENDTRNS") ){
      $this->malformed_msg = "The record is SPL or ENDTRNS, but didn't start with TRNS";
      return TRUE;
    }

    if( $this->array_count($data) > count($this->default_headers[$data[0]]) ){
      $this->malformed_msg = "There are more elements in the record, ".count($data).", than in the header, ".count($this->default_headers[$data[0]]);
      return TRUE;
    }

    //Check these conditions if not in the first record of the file
    if( isset($this->prior_record) ){
      if( $this->prior_record[0] == "TRNS" AND $data[0] !== "SPL" ){
        $this->malformed_msg = "A TRNS record is not followed by an SPL record";
        return TRUE;
      }

      if( $this->prior_record[0] == "SPL" AND !in_array($data[0], array("SPL", "ENDTRNS")) ){
        $this->malformed_msg = "An SPL record is not followed by another SPL record or ENDTRNS";
        return TRUE;
      }
    }
    
    //Reaching this point means that the record is not malformed
    return FALSE;
  }

  ///This function helps determine whether the line in a datafile is malformed.  It determines the last non-null or non-"" elements in the line, and whether it exceeds the last element of the header for that line.
  private function array_count($data){
    $last_element  = 0;
    foreach($data as $key=>$value){
      if(!is_null($value) AND $value !== ""){
        $last_element = $key + 1;
      }
    }
    return $last_element;
  }
}

?>
