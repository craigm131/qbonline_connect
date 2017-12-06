<?php

/// This is the QboSqlManager class.  It manages writing and retrieving data from a SQL database, including creating the database itself.
// 	This code was created several years ago - an early PHP project.
//  Developer:  Craig Millis

/**
 *  @param  str   $host           eg "home.com"
 *  @param  str   $username       eg "root"
 *  @param  str   $password       eg
 *  @param  str   $home_client   eg "jlls"
 */

foreach (glob(__DIR__."/PHPMailer-master/*.php") as $filename){
  require_once($filename);
}
require_once("qbo_transaction.php");

class QboSqlManager {

	/// VARIABLES
  public    $mysqli;
  public    $filename;
  public    $homefile_id;
  public    $reports;
  private   $host;
  private   $username;
  private   $home_client;
  private   $toReceiveDir;
  private   $dir;
  protected $IPP;
	protected $context;
	protected $realm;
	protected $creds;

	function __construct($host, $username, $password, $home_client, $toReceiveDir, $dir) {
    $this->host         = $host;
    $this->username     = $username; 
    $this->password     = $password;
    $this->home_client 	= $home_client;
    $this->toReceiveDir = $toReceiveDir;
    $this->dir          = $dir;//directory to temporarily hold reports before moving to toReceiveDir
    $this->mysqli       = new mysqli($host, $username, $password);
    $this->reports      = array();

		if ($this->mysqli->connect_error) {
			throw new Exception('Connect Error (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
		}
		else
		{
			if($this->mysqli->select_db($home_client))
			{
				echo "\n".__FILE__.": Connected to database $home_client.";
				//$this->mysqli = new mysqli("oh52.home.com", "root", "0qanNxX)z3+G|1", $home_client);
			}
			else
			{
				echo "\nDatabase $home_client does not exist.  Creating database.";
				$this->create_client_db($home_client);
				$this->mysqli = new mysqli($host, $username, $password, $home_client);
			}
    }

    // Connect to QBO 
		// Set up the IPP instance
		$this->IPP = new QuickBooks_IPP(QUICKBOOKS2_DSN);

    // Setup IntuitAnywhere
    /* The following parameters are passed, for example:
       QUICKBOOKS2_DSN               mysqli://craig:password@oh52.home.com/qbo_admin
       QUICKBOOKS2_ENCRYPT_KEY       abcd1234
       QUICKBOOKS2_CONSUMER_KEY      longkeystring
       QUICKBOOKS2_CONUSMER_SECRET   longsecretstring
       QUICKBOOKS2_OAUTH_URL         /htdocs/qbo/oauth.php
       QUICKBOOKS2_SUCCESS_URL       /htdocs/qbo/success.php 
    $paramArray = array(QUICKBOOKS2_DSN, QUICKBOOKS2_ENCRYPT_KEY, QUICKBOOKS2_CONSUMER_KEY, QUICKBOOKS2_CONSUMER_SECRET, QUICKBOOKS2_OAUTH_URL, QUICKBOOKS2_SUCCESS_URL);
    foreach($paramArray as $key=>$value){
      echo "\n".$key." ".$value;
    }
    */
		$IntuitAnywhere = new QuickBooks_IPP_IntuitAnywhere(QUICKBOOKS2_DSN, QUICKBOOKS2_ENCRYPT_KEY, QUICKBOOKS2_CONSUMER_KEY, QUICKBOOKS2_CONSUMER_SECRET, QUICKBOOKS2_OAUTH_URL, QUICKBOOKS2_SUCCESS_URL);

    // Get our OAuth credentials from the database
    /* The following parameters are passed, for example:
        QUICKBOOKS2_USERNAME    DO_NOT_CHANGE_ME
        QUICKBOOKS2_TENANT      jlls
    */ 
		$this->creds = $IntuitAnywhere->load(QUICKBOOKS2_USERNAME, QUICKBOOKS2_TENANT);

		// Tell the framework to load some data from the OAuth store.  Craig010215: tells us we're in 'oauth' mode.
		$this->IPP->authMode(
			QuickBooks_IPP::AUTHMODE_OAUTH, 
			QUICKBOOKS2_USERNAME, 
			$this->creds);
		
		// Print the credentials we're using
		//print_r($creds);

		// This is our current realm
		$this->realm = $this->creds['qb_realm'];

		// Load the OAuth information from the database
		if ($this->context = $this->IPP->context()){
			// Set the IPP version to v3 
			$this->IPP->version(QuickBooks_IPP_IDS::VERSION_3);
    }

    //Create the object that will access QBO
    try{
      $this->qbo_trnx_obj = new qbo_transaction($this->context, $this->realm, $this->mysqli);
    }catch (Exception $e){
      var_dump($e->getMessage());
    }  
	}
    
  function setFilename($filename){
    $this->filename = $filename;
  }

  /// This function obtains the unique id that identifies the most recent version of the $filename in the sql db
  function getHomefile_id($filename) {
    $sql = "SELECT homefile_id FROM homefiles WHERE file_name = '$filename'";
    $result = $this->mysqli->query($sql);

    // Get the unique file homefile_id
    if ($result == TRUE){
      while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        $homefile_id = $row['homefile_id'];
      }
      return $homefile_id;
    }else{
      echo $this->mysqli->error;
      return;
    }
  }

  /// This function closes the db
	public function close_db() {
		if ($this->mysqli !== NULL) {
			$this->mysqli->close();
		}
	}

	/// Close the db before it's destroyed
	public function __destruct() {
		$this->close_db();
	}

  /// Create a client's sql db if it doesn't exist
	public function create_client_db($home_client){
	    $sql = "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
		SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
		SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES';

		-- -----------------------------------------------------
		-- Schema $home_client
		-- -----------------------------------------------------

		-- -----------------------------------------------------
		-- Schema $home_client
		-- -----------------------------------------------------
		CREATE SCHEMA IF NOT EXISTS `$home_client` ;
		USE `$home_client` ;

		-- -----------------------------------------------------
		-- Table `$home_client`.`homefiles`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`homefiles` (
		  `homefile_id` INT NOT NULL AUTO_INCREMENT,
		  `file_name` VARCHAR(45) NULL,
		  `creation_timestamp` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'datetime when the data was imported into MySQL',
		  `attempted_start_timestamp` TIMESTAMP NULL,
		  `attempted_end_timestamp` TIMESTAMP NULL,
		  PRIMARY KEY (`homefile_id`))
		ENGINE = InnoDB;


		-- -----------------------------------------------------
		-- Table `$home_client`.`vendors`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`vendors` (
		  `vendor_id` INT NOT NULL AUTO_INCREMENT,
		  `trns_cat` VARCHAR(45) NULL,
		  `account_no` VARCHAR(45) NOT NULL COMMENT 'In Home, this is unique - it is the account name concatenated with account no.',
		  `printas` VARCHAR(45) NULL,
		  `addr1` VARCHAR(45) NULL,
		  `addr2` VARCHAR(45) NULL,
		  `addr3` VARCHAR(45) NULL,
		  `addr4` VARCHAR(45) NULL,
		  `addr5` VARCHAR(45) NULL,
		  `vtype` VARCHAR(45) NULL,
		  `cont1` VARCHAR(45) NULL,
		  `cont2` VARCHAR(45) NULL,
		  `phone1` VARCHAR(45) NULL,
		  `phone2` VARCHAR(45) NULL,
		  `faxnum` VARCHAR(45) NULL,
		  `email` VARCHAR(45) NULL,
		  `note` VARCHAR(45) NULL,
		  `taxid` VARCHAR(45) NULL,
		  `limit` VARCHAR(45) NULL,
		  `terms` VARCHAR(45) NULL,
		  `salutation` VARCHAR(45) NULL,
		  `companyname` VARCHAR(45) NULL,
		  `firstname` VARCHAR(45) NULL,
		  `midinit` VARCHAR(45) NULL,
		  `lastname` VARCHAR(45) NULL,
		  `1099` TINYINT(1) NULL,
		  `hidden` VARCHAR(45) NULL,
		  `delcount` VARCHAR(45) NULL,
		  `qb_val` VARCHAR(45) NOT NULL COMMENT 'Marked as \'PENDING\' when Database copies to MySQL and then copied over with value returned from Quickbooks when data passed to Quickbooks.',
		  `qb_note` VARCHAR(400) NULL,
		  `qb_timestamp` DATETIME NULL,
		  `vendor_ref` VARCHAR(12) NULL COMMENT 'unique number assigned by QB',
		  `homefile_id` INT NOT NULL,
		  PRIMARY KEY (`vendor_id`),
		  INDEX `homefile_id_idx` (`homefile_id` ASC),
		  CONSTRAINT `1homefile_id`
		    FOREIGN KEY (`homefile_id`)
		    REFERENCES `$home_client`.`homefiles` (`homefile_id`)
		    ON DELETE CASCADE
		    ON UPDATE CASCADE)
		ENGINE = InnoDB;


		-- -----------------------------------------------------
		-- Table `$home_client`.`customers`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`customers` (
		  `customer_id` INT NOT NULL AUTO_INCREMENT,
		  `trns_cat` VARCHAR(45) NULL,
		  `account_no` VARCHAR(45) NOT NULL,
		  `baddr1` VARCHAR(45) NULL,
		  `baddr2` VARCHAR(45) NULL,
		  `baddr3` VARCHAR(45) NULL,
		  `baddr4` VARCHAR(45) NULL,
		  `baddr5` VARCHAR(45) NULL,
		  `saddr1` VARCHAR(45) NULL,
		  `saddr2` VARCHAR(45) NULL,
		  `saddr3` VARCHAR(45) NULL,
		  `saddr4` VARCHAR(45) NULL,
		  `saddr5` VARCHAR(45) NULL,
		  `phone1` VARCHAR(45) NULL,
		  `phone2` VARCHAR(45) NULL,
		  `faxnum` VARCHAR(45) NULL,
		  `email` VARCHAR(45) NULL,
		  `note` VARCHAR(45) NULL,
		  `cont1` VARCHAR(45) NULL,
		  `cont2` VARCHAR(45) NULL,
		  `ctype` VARCHAR(45) NULL,
		  `terms` VARCHAR(45) NULL,
		  `taxable` TINYINT(1) NULL,
		  `salestaxcode` VARCHAR(45) NULL,
		  `limit` VARCHAR(45) NULL,
		  `rep` VARCHAR(45) NULL,
		  `salutation` VARCHAR(45) NULL,
		  `companyname` VARCHAR(45) NULL,
		  `firstname` VARCHAR(45) NULL,
		  `midinit` VARCHAR(45) NULL,
		  `lastname` VARCHAR(45) NULL,
		  `jobstatus` VARCHAR(45) NULL,
		  `hidden` VARCHAR(45) NULL,
		  `delcount` VARCHAR(45) NULL,
		  `qb_val` VARCHAR(45) NOT NULL COMMENT 'Marked as \'PENDING\' when Database copies to MySQL and then copied over with value returned from Quickbooks when data passed to Quickbooks.',
		  `qb_note` VARCHAR(400) NULL,
		  `qb_timestamp` DATETIME NULL,
		  `customer_ref` VARCHAR(12) NULL COMMENT 'unique number assigned by QB',
		  `homefile_id` INT NOT NULL,
		  PRIMARY KEY (`customer_id`),
		  INDEX `homefile_id_idx` (`homefile_id` ASC),
		  CONSTRAINT `2homefile_id`
		    FOREIGN KEY (`homefile_id`)
		    REFERENCES `$home_client`.`homefiles` (`homefile_id`)
		    ON DELETE CASCADE
		    ON UPDATE CASCADE)
		ENGINE = InnoDB;


		-- -----------------------------------------------------
		-- Table `$home_client`.`acctg`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`acctg` (
		  `acctg_id` INT NOT NULL AUTO_INCREMENT,
		  `homefile_id` INT NOT NULL,
		  `entry_id` INT NOT NULL,
		  `qb_val` VARCHAR(45) NOT NULL COMMENT 'Marked as \'PENDING\' when Database copies to MySQL and then copied over with value returned from Quickbooks when data passed to Quickbooks.',
		  `qb_note` VARCHAR(400) NULL,
		  `qb_timestamp` VARCHAR(45) NULL COMMENT 'datetime that data was passed to Quickbooks',
		  PRIMARY KEY (`acctg_id`),
		  INDEX `homefile_id_idx` (`homefile_id` ASC),
		  UNIQUE INDEX `acctg_id_UNIQUE` (`acctg_id` ASC),
		  CONSTRAINT `3homefile_id`
		    FOREIGN KEY (`homefile_id`)
		    REFERENCES `$home_client`.`homefiles` (`homefile_id`)
		    ON DELETE CASCADE
		    ON UPDATE CASCADE)
		ENGINE = InnoDB;


		-- -----------------------------------------------------
		-- Table `$home_client`.`trns`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`trns` (
		  `trns_id` INT NOT NULL AUTO_INCREMENT,
		  `trns_cat` VARCHAR(45) NOT NULL,
		  `trnstype` VARCHAR(45) NOT NULL,
		  `date` DATE NULL,
		  `accnt` VARCHAR(300) NULL,
		  `name` VARCHAR(45) NULL,
		  `class` VARCHAR(45) NULL,
		  `amount` FLOAT NULL,
		  `docnum` VARCHAR(45) NULL,
		  `memo` VARCHAR(45) NULL,
		  `clear` VARCHAR(45) NULL,
		  `toprint` VARCHAR(45) NULL,
		  `paymeth` VARCHAR(45) NULL,
		  `duedate` DATE NULL,
		  `terms` VARCHAR(45) NULL,
		  `addr1` VARCHAR(45) NULL,
		  `addr2` VARCHAR(45) NULL,
		  `addr3` VARCHAR(45) NULL,
		  `paid` VARCHAR(45) NULL,
		  `shipdate` DATE NULL,
		  `ponum` VARCHAR(45) NULL,
		  `invtitle` VARCHAR(45) NULL,
      `invmemo` VARCHAR(45) NULL,
      `batch` VARCHAR(45) NULL,
		  `entry_id` INT NULL,
		  `entry_line` INT NULL,
		  `customer_ref` VARCHAR(12) NULL COMMENT 'The unique number that Quickbooks assigns to customers when their account is created.  Determined by using the Pro# from Home and comparing to lookup table in MySQL that was populated when customer numbers were created.',
		  `vendor_ref` VARCHAR(12) NULL,
		  `account_ref` VARCHAR(45) NULL,
		  `bank_account_ref` VARCHAR(45) NULL,
		  `txn_id` VARCHAR(12) NULL COMMENT 'Transaction ID assigned by qb when an invoice or bill was created.  Determined by using the',
		  `acctg_id` INT NOT NULL,
		  PRIMARY KEY (`trns_id`),
		  INDEX `ae_id_idx` (`acctg_id` ASC),
		  UNIQUE INDEX `acctg_id_UNIQUE` (`acctg_id` ASC),
		  CONSTRAINT `4a_id`
		    FOREIGN KEY (`acctg_id`)
		    REFERENCES `$home_client`.`acctg` (`acctg_id`)
		    ON DELETE CASCADE
		    ON UPDATE CASCADE)
		ENGINE = InnoDB;


		-- -----------------------------------------------------
		-- Table `$home_client`.`spl`
		-- -----------------------------------------------------
		CREATE TABLE IF NOT EXISTS `$home_client`.`spl` (
		  `spl_id` INT NOT NULL AUTO_INCREMENT,
		  `trns_cat` VARCHAR(45) NOT NULL,
		  `trnstype` VARCHAR(45) NOT NULL,
		  `date` DATE NULL,
		  `accnt` VARCHAR(300) NULL,
		  `name` VARCHAR(45) NULL,
		  `class` VARCHAR(45) NULL,
		  `amount` FLOAT NULL,
		  `docnum` VARCHAR(45) NULL,
		  `memo` VARCHAR(45) NULL,
		  `clear` VARCHAR(45) NULL,
		  `qnty` INT NULL,
		  `price` FLOAT NULL,
		  `invitem` VARCHAR(45) NULL,
		  `entry_id` INT NULL,
		  `entry_line` INT NULL,
		  `account_ref` VARCHAR(45) NULL,
		  `item_ref` VARCHAR(12) NULL,
		  `txn_id` VARCHAR(45) NULL,
		  `acctg_id` INT NOT NULL,
		  PRIMARY KEY (`spl_id`),
		  INDEX `ae_id_idx` (`acctg_id` ASC),
		  CONSTRAINT `5a_id`
		    FOREIGN KEY (`acctg_id`)
		    REFERENCES `$home_client`.`acctg` (`acctg_id`)
		    ON DELETE CASCADE
		    ON UPDATE CASCADE)
		ENGINE = InnoDB;

    -- -----------------------------------------------------
    -- Table `$home_client`.`malformed`
    -- -----------------------------------------------------
    CREATE TABLE IF NOT EXISTS `$home_client`.`malformed` (
      `malformed_id` INT NOT NULL AUTO_INCREMENT,
      `trns_cat` VARCHAR(45) NOT NULL,
      `value` VARCHAR(400) NULL,
      `note` VARCHAR(45) NULL,
      `homefile_id` INT NOT NULL,
      PRIMARY KEY (`malformed_id`),
      INDEX `homefile_id_idx` (`homefile_id` ASC),
      CONSTRAINT `4homefile_id`
        FOREIGN KEY (`homefile_id`)
        REFERENCES `$home_client`.`homefiles` (`homefile_id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE)
    ENGINE = InnoDB;

		SET SQL_MODE=@OLD_SQL_MODE;
		SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
		SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;";

	    $mysqli = $this->mysqli;  
	    if ($mysqli->multi_query($sql)){
	    	echo "\nDatabase $home_client created. Yo!";
	    }else{
	    	// throw an error
			throw new Exception("Database $home_client not created - it may already exist.");
	    }
	    
	    $mysqli->close();

	    return;
	}

  /// Clean up the sql db, deleting records that are greater than XX days old
  function cleanDb($ageOfFiles){
    $date = date("Y-m-d", strtotime("-".$ageOfFiles." days")); 
    
    $sql = "DELETE FROM homefiles
      WHERE creation_timestamp <=".$date;
    
    $result = $this->mysqli->query($sql);
    
    if($result == TRUE){
      //echo "\n".basename(__FILE__).": deleted MySQL records that were more than ".$ageOfFiles." days old";
    }else{
      echo "\n".basename(__FILE__).": failed to delete MySQL records that were more than ".$ageOfFiles." days old";
    }
  }

  /// Create .err report
  function createErr() {
    $filepath         = $this->dir . pathinfo($this->filename, PATHINFO_FILENAME) . ".err";
    $db               = fopen($filepath, 'w');
    $homefile_id     = $this->homefile_id;
    $row              = NULL;
    $recordMalformed  = FALSE;
    
    // Insert the header
    fputcsv($db, array('!TRNS', 'TRNSTYPE', 'DATE', 'NAME', 'AMOUNT', 'DOCNUM', 'BATCH'));

    $trnstype 	  = array(
      'CHECK'       =>'billpayment', 
      'JNL_CHK'     =>'journalentry', 
      'PAYMENT'     =>'PAYMENT', 
      'JNL_PMT'     =>'journalentry', 
      'INVOICE'     =>'INVOICE', 
      'CREDIT MEMO' =>'creditmemo', 
      'BILL'        =>'BILL', 
      'BILL REFUND' =>'vendorcredit'
    );

    // set the variable for querying the accounting ids of the journal entries
    //$acctg_ids = "SELECT acctg_id FROM acctg WHERE homefile_id = '$homefile_id' AND qb_val = 'PENDING'";
    $acctg_ids = "SELECT acctg_id FROM acctg 
                  WHERE homefile_id = '$homefile_id'
                  AND qb_note NOT LIKE 'Our%'";

    $sql_trns     = "
      SELECT trns_cat, trnstype, date, name, amount, docnum, batch 
      FROM trns 
      WHERE acctg_id IN (".$acctg_ids.")";
    $result_trns  = $this->mysqli->query($sql_trns);

    // Record all trnstypes and adjust for JNL_CHK
    if ($result_trns !== FALSE){
      while ( $row = mysqli_fetch_array($result_trns, MYSQLI_ASSOC) ){
        $row['date'] = date("m/d/y", strtotime($row['date']));
        fputcsv($db, $row);
      }
    }
    
    // Record malformed records from datafile
    if($recordMalformed == TRUE){
      fputcsv($db, array(''));
      fputcsv($db, array('!MALFORMED', 'VALUE', 'NOTE'));

      $sql_malformed = "
        SELECT trns_cat, value, note
        FROM malformed
        WHERE homefile_id = '$homefile_id'";
      $result_malformed = $this->mysqli->query($sql_malformed);

      $row = NULL;
      if ($result_malformed !== FALSE){
        while ( $row = mysqli_fetch_array($result_malformed, MYSQLI_ASSOC) ){
          fputcsv($db, $row);
        }
      }
    }
    fclose($db); 
    return $filepath;
  }

  /// Create success report
  function createSuccess() {
    $filepath     = $this->dir . pathinfo("success".$this->filename, PATHINFO_FILENAME) . ".csv";
    $db           = fopen($filepath, 'w');
    $homefile_id = $this->homefile_id; 
    
    // Insert the header
    fputcsv($db, array('!TRNS', 'TRNSTYPE', 'DATE', 'NAME', 'AMOUNT', 'DOCNUM', 'BATCH'));

    $trnstype 	  = array(
      'CHECK'       =>'billpayment', 
      'JNL_CHK'     =>'journalentry', 
      'PAYMENT'     =>'PAYMENT', 
      'JNL_PMT'     =>'journalentry', 
      'INVOICE'     =>'INVOICE', 
      'CREDIT MEMO' =>'creditmemo', 
      'BILL'        =>'BILL', 
      'BILL REFUND' =>'vendorcredit'
    );

    // set the variable for querying the accounting ids of the journal entries
    $acctg_ids = "SELECT acctg_id FROM acctg 
                  WHERE homefile_id = '$homefile_id'
                  AND qb_note LIKE 'Our%'";

    $sql_trns     = "
      SELECT trns_cat, trnstype, date, name, amount, docnum, batch 
      FROM trns 
      WHERE acctg_id IN (".$acctg_ids.")";
    $result_trns  = $this->mysqli->query($sql_trns);
    
    // Record all trnstypes and adjust for JNL_CHK
    if ($result_trns !== FALSE){
      while ( $row = mysqli_fetch_array($result_trns, MYSQLI_ASSOC) ){
        $row['date'] = date("m/d/y", strtotime($row['date']));
        fputcsv($db, $row);
      }
    } 
    fclose($db);
    return $filepath;
  }

	/// This function creates a report that shows all of the entries PLUS the response from QBO after attempting to record in QBO
	function createTotalReport() {
    $home_client = $this->home_client;
    $filename     = $this->filename;
    $dir          = $this->dir;
    $mysqli       = $this->mysqli;
    $filepath     = $dir . "totalReport".pathinfo($filename, PATHINFO_FILENAME) . ".csv";
    $db           = fopen($filepath, 'w');
    
    $tbls 		= array('vendor_id'=>'vendors', 'customer_id'=>'customers', 'acctg_id'=>'acctg');
    $classes 	= array('INVOICE'=>'INVOICE', 'BILL'=>'BILL', 'GENERAL JOURNAL'=>'journalentry', 'PAYMENT'=>'PAYMENT', 'CHECK'=>'billpayment', 'CREDIT MEMO'=>'creditmemo', 'BILL REFUND'=>'vendorcredit');

    $sql = "SELECT homefile_id FROM homefiles WHERE file_name = '$filename'";

    $result = $mysqli->query($sql);

    if ($result == TRUE) {
      while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)){
        $homefile_id = $row['homefile_id'];
      }
    }else{
      echo $mysqli->error;
      return;
    }
    foreach ($tbls as $key=>$tbl){
      if ($tbl !== 'acctg'){
        // get vendor and customer data
        $sql = "SELECT * FROM $tbl WHERE homefile_id = '$homefile_id'";
        $result = $mysqli->query($sql);
        if ($mysqli->query($sql) == TRUE){
          $flip = true;
          while ($row = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            //slice off the first column
            $row = array_slice($row, 1);
    
            //insert the keys
            if($flip){
              $flip = false;
              $keys = array_keys($row);
              if($tbl == 'vendors'){
                $keys[array_search('trns_cat', $keys)] = '!vend';
                $keys[array_search('0', $keys)] = '1099';
              }else{
                $keys[array_search('trns_cat', $keys)] = '!cust';
              }
              $row = array_combine($keys, $row);
              fputcsv($db, array_keys(array_change_key_case($row, CASE_UPPER)));	
            }
            fputcsv($db, $row);	
          }
        }else{
          echo $mysqli->error;
        }
      }else{
        // get acctg entries with trns and spl data
        $sql = "SELECT $key,
        entry_id,
        qb_val,
        qb_note,
        qb_timestamp
        FROM $tbl WHERE homefile_id = '$homefile_id'";

        $result = $mysqli->query($sql);

        if ($result == TRUE){	
          $flip = true;
          while ($row = mysqli_fetch_array($result,MYSQLI_ASSOC)){
            //fputcsv($db, $row);	

            // get trns side of acctg
            $sql_trns 	= "SELECT * FROM trns WHERE acctg_id 	= '" .$row['acctg_id']. "'";
            $result_trns = $mysqli->query($sql_trns);

            $sql_spl 	= "SELECT * FROM spl WHERE acctg_id 	= '" .$row['acctg_id']. "'";
            $result_spl = $mysqli->query($sql_spl);

            if ($result_trns == TRUE && $result_spl == TRUE){	
              $row_spl = mysqli_fetch_array($result_spl,MYSQLI_ASSOC);
              while ($row_trns = mysqli_fetch_array($result_trns,MYSQLI_ASSOC)){
                //slice off the first column
                $row_trns = array_slice($row_trns, 1);
                $row_spl = array_slice($row_spl, 1);
                $row_trns = array_merge($row_trns, $row);//for each transaction, combine the trns to acctg to show the QB results
                
                //insert the the trns, spl, and endtrns headers
                if($flip){
                  $flip = false;
                  $keys = array_keys($row_trns);
                  $keys[array_search('trns_cat', $keys)] = '!trns';
                  $row_trns = array_combine($keys, $row_trns);
                  fputcsv($db, array_keys(array_change_key_case($row_trns, CASE_UPPER)));//insert the trns merged with acctg header	

                  $keys = array_keys($row_spl);
                  $keys[array_search('trns_cat', $keys)] = '!spl';
                  $row_spl = array_combine($keys,$row_spl);
                  fputcsv($db, array_keys(array_change_key_case($row_spl, CASE_UPPER)));//insert the spl header	
                  fputcsv($db, array(0=>'!ENDTRNS'));
                }

                fputcsv($db, $row_trns);	
              }
              //unset($row_spl);
              $result_spl = $mysqli->query($sql_spl);
              while ($row_spl = mysqli_fetch_array($result_spl,MYSQLI_ASSOC)){
                //slice off the first column
                $row_spl = array_slice($row_spl, 1);
                
                fputcsv($db, $row_spl);
              }
              fputcsv($db, array(0=>'ENDTRNS'));
            }else{
              echo $mysqli->error;
            }
          }
        }else{
          echo $mysqli->error;
        }
      }
    }
    fclose($db);
	  return $filepath;
  }

  function moveFileCopyToReceiveDir($sourcePath){
    $destPath = $this->toReceiveDir.basename($sourcePath);
    $tempPath = dirname($sourcePath)."/tmp".basename($sourcePath);
    if(copy($sourcePath, $tempPath)){
      if(!rename($sourcePath, $destPath)){
        echo "\n".basename(__FILE__).": failed to move ".basename($sourcePath)." to ".dirname($destPath);
      }
      rename($tempPath, $sourcePath);
    }else{
      echo "\n".basename(__FILE__).": failed to make a copy of ".basename($sourcePath)." in ".dirname($destPath);
      echo "\n".basename(__FILE__).": failed to move ".basename($sourcePath)." to ".dirname($destPath);
    }
  }

  public function qb_log($log, $type){
    //$log[0] is TRUE or FALSE depending on whether the record was written to QBO
    $resp 		= $log[1];	//the message returned by QBO
    $name 		= $log[2];	//the name, or id, of the related customer/vendor
    $acctg_id = $log[3];	//the unique MySQL id of the acctg record
    $mysqli   = $this->mysqli;
    $resp     = $mysqli->real_escape_string($resp);
    $name     = $mysqli->real_escape_string($name);

    if($type == 'customer' || $type == 'vendor')
    {
      //log for customer and vendor
      // check to see if record was copied to QBO
      if($log[0] == TRUE) {
        // Log success
        // Update MySQL record (table, set field, set value, where field, where value)
        $resp = str_replace(array('[', ']', '{', '}', '-'), '', $resp);
        $this->update_mysql($type . 's', 'qb_val'				, 'RECORDED', 							'account_no', $name); // record QB val
        $this->update_mysql($type . 's', 'qb_note'				, 'Our new ID is: [' . $resp . ']', 	'account_no', $name); // record QB note
        $this->update_mysql($type . 's', 'qb_timestamp'		, date('Y-m-d H:i:s'), 					'account_no', $name); // record QB note
        $this->update_mysql($type . 's', $type . '_ref'		, $resp, 								'account_no', $name); // record QB's unique customer number
      }
      else
      {
        // Log failure
        // Update MySQL record (table, set field, set value, where field, where value)
        $this->update_mysql($type . 's', 'qb_note'			, $resp,				'account_no', $name);
        $this->update_mysql($type . 's', 'qb_timestamp'	, date('Y-m-d H:i:s'), 	'account_no', $name);
      }
    }
    else
    {
      //log for acctg
      // check to see if record was copied to QBO
      if($log[0] == TRUE) {
        // Log success
        // Update MySQL record (table, set field, set value, where field, where value, connection, path to datafile, datafile name)
        $resp = str_replace(array('[', ']', '{', '}', '-'), '', $resp);
        $this->update_mysql('acctg', 	'qb_val', 		'RECORDED',							'acctg_id', $acctg_id);
        $this->update_mysql('acctg', 	'qb_note', 		'Our new ID is: [' . $resp . ']',	'acctg_id', $acctg_id);
        $this->update_mysql('acctg', 	'qb_timestamp', date('Y-m-d H:i:s'), 				'acctg_id', $acctg_id);
        $this->update_mysql('trns', 	'txn_id', 		$resp,								'acctg_id', $acctg_id);
      }
      else
      {
        // Log failure
        $this->update_mysql('acctg', 	'qb_note', 		$resp,								'acctg_id', $acctg_id);
        $this->update_mysql('acctg', 	'qb_timestamp', date('Y-m-d H:i:s'), 				'acctg_id', $acctg_id);
      }
    }
  }

  ///This function updates a field in a client's sql db, particularly responses from QBO
  //This function is called by the qb_log function.
  private function update_mysql($tbl, $set_field, $set_value, $where_field, $where_value){
    $mysqli = $this->mysqli;

    $sql = "UPDATE $tbl SET $set_field = '$set_value' WHERE $where_field = '$where_value'";

    if ($mysqli->query($sql) !== TRUE){
      echo $mysqli->error;
    }
  }

  //Copy records to QBO
  private function qb_call($arr, $trnstype) {
    if (isset($trnstype) && is_string($trnstype)) {
      // update trnstype to match our format
      $create_trnstype = "create_" . $trnstype;
    }else{
      throw new Exception('bad script initializer - home_qb_master must be called with passed command');
    }

    try{
      $log = $this->qbo_trnx_obj->$create_trnstype($arr); 
    }catch (Exception $e){
      var_dump($e->getMessage());
    }

    /*
    // create QBO transaction object with functions for creating customer, vendor, invoice, bill, payment, billpayment, creditmemo...
    try {
      $obj = new qbo_transaction($this->context, $this->realm, $this->mysqli);
      $log = $obj->$create_trnstype($arr);
    }catch (Exception $e) {
      var_dump($e->getMessage());
    }
    */
  }

  ///This function queries MySQL for the homefile_id and any transactions marked as pending, and passes those to qb_call.
  //This function is called by home_qb_master.php
  public function writeToQbo(){	
    $homefile_id       = $this->homefile_id;
    $filename           = $this->filename;
    $mysqli             = $this->mysqli;
    $field_map          = array();

    // clear our line inputs because qb_call will trigger on them.
		$argv = $argc = NULL;

		//obtain $field_map array to translate home field name to quickbooks field name
    $field_map = $this->getFieldMap();

    //record the time at the beginning of the process
    $datetime = date('Y-m-d H:i:s');
    $sql_timestamp = "UPDATE homefiles SET attempted_start_timestamp = '$datetime' WHERE homefile_id = '$homefile_id'";
    if ($mysqli->query($sql_timestamp) == TRUE){
      // Copy vendors and customers to Quickbooks
      foreach (array('customer', 'vendor') as $k=>$v){
        $vs = $v . "s";

        $sql = "SELECT * FROM $vs WHERE qb_val='PENDING' AND homefile_id = '$homefile_id'";
        if(($result = $mysqli->query($sql)) !== FALSE){
          if($result->num_rows > 0){
            $arr = array();
            $i = 0;
            while ($row	= $result->fetch_array(MYSQLI_ASSOC)){
              $row_qb = array();
              foreach ($row as $key=>$value){	
                if ($field_map[$vs][$key] !== NULL && $field_map[$vs][$key] !== ""){
                  $row_qb[$field_map[$vs][$key]] = $value; //convert from Home field names to Quickbooks field names
                }
              }
              $row_qb = array_filter($row_qb, 'strlen');// 
              $arr[$i] = $row_qb;
              $i++;
            }
            echo "\n\n*******".$filename. ": Processing ". $v ."s...".count($arr). " records";

            //pass the string of data and Home's unique number assigned to the vendor/customer
            $this->qb_call($arr, $v); 
          }else{
            echo "\n\n*******".$filename. ": Processing ". $v ."s...no records";
          }
        }
      }

      // Copy the accounting entries to Quickbooks
      foreach (array('INVOICE', 'BILL', 'PAYMENT', 'CHECK', 'CREDIT MEMO', 'BILL REFUND', 'GENERAL JOURNAL') as $k=>$v) {

        $sql = "SELECT * FROM trns WHERE trns.acctg_id IN (SELECT acctg_id FROM acctg WHERE qb_val = 'PENDING' AND homefile_id = '$homefile_id') AND trns.trnstype = '$v'";    
        
        if(($trns_rst = $mysqli->query($sql)) !== FALSE){
          if($trns_rst->num_rows > 0){
            $trns = array();
            while ($row = $trns_rst->fetch_array(MYSQLI_ASSOC)) {
              $trns[$row['acctg_id']][] = $row;
            }

            //convert from Home field names to Quickbooks field names for trns
            $trns_qb=array();
            foreach ($trns as $k1=>$entry) {
              foreach ($entry as $k2=>$entry_line) {
                foreach ($entry_line as $k3=>$value) {
                  if ($field_map["$v.trns"][$k3] !== NULL) {
                    if ($value!==$v) { // remove the trnstype field because Quickbooks won't accept it
                      $trns_qb[$k1][$k2][$field_map["$v.trns"][$k3]] = $value; 
                      $trns_qb[$k1][$k2] = array_filter($trns_qb[$k1][$k2], 'strlen');
                    }
                  }
                }
              }
            }

            $sql = "SELECT * FROM spl WHERE spl.acctg_id IN (SELECT acctg_id FROM acctg WHERE qb_val = 'PENDING' AND homefile_id = '$homefile_id') AND spl.trnstype = '$v'"; 
            $spl_rst = $mysqli->query($sql);
            $spl = array();
            while ($row = $spl_rst->fetch_array(MYSQLI_ASSOC)) {
              $spl[$row['acctg_id']][] = $row;
            }

            //convert from Home field names to Quickbooks field names for spl
            $spl_qb=array();
            foreach ($spl as $k1=>$entry) {
              foreach ($entry as $k2=>$entry_line) {
                foreach ($entry_line as $k3=>$value) {
                  if ($field_map["$v.spl"][$k3] !== NULL) {
                    if ($value!==$v) { // remove the trnstype field (eg INVOICE=>INVOICE) because Quickbooks won't accept it
                      $spl_qb[$k1][$k2][$field_map["$v.spl"][$k3]] = $value; 
                      $spl_qb[$k1][$k2] = array_filter($spl_qb[$k1][$k2], 'strlen');
                    }
                  }
                }
              }
            }

            $arr = array();

            foreach($trns_qb as $key=>$value) {
              $arr[$key] = array("trns"=>$trns_qb[$key], "spl"=>$spl_qb[$key]);
            }

            //order of copying transactions: invoices, bills, payments, billpayments, credit memos, bill refunds, general journals
            $class = array('INVOICE'=>'INVOICE', 'BILL'=>'BILL', 'GENERAL JOURNAL'=>'journalentry', 'PAYMENT'=>'PAYMENT', 'CHECK'=>'billpayment', 'CREDIT MEMO'=>'creditmemo', 'BILL REFUND'=>'vendorcredit');
            echo "\n\n*******".$filename. ": Processing ". $v ."S...".count($arr). " records";
                        
            //this sends all transactions of a particular trnstype to be copied (eg all invoices are copied to Quickbooks)
            $this->qb_call($arr, $class[$v]);
          }else{
            echo "\n\n*******".$filename. ": Processing ". $v ."S...no records";
          }
        }
      }
      //record the time at the end of the process
      $datetime = date('Y-m-d H:i:s');
      $sql_timestamp = "UPDATE homefiles SET attempted_end_timestamp = '$datetime' WHERE homefile_id = '$homefile_id'";
      $mysqli->query($sql_timestamp);
		}else{
			echo "\nNo files in MySQL to copy to Quickbooks";
		}
	}

  private function getFieldMap(){
    $mysqli = $this->mysqli;

    // return name of current default database
    if ($result = $mysqli->query("SELECT DATABASE()")) {
       $row = $result->fetch_row();
       //printf("Default database is %s.\n", $row[0]);
       $result->close();
    }

    // change db to world db
    $mysqli->select_db("field_map");// return name of current default database
    if ($result = $mysqli->query("SELECT DATABASE()")) {
       $row = $result->fetch_row();
       //printf("Default database is %s.\n", $row[0]);
       $result->close();
    }

    $field_map = array();

    $type_array = array('vendors', 'customers');
    foreach ($type_array as $key=>$value) {
      $sql = "SELECT * FROM $value";
      if($result = $mysqli->query($sql)) {
        While ($row = $result->fetch_array(MYSQLI_ASSOC)) {
          $field_map[$value] = $row;
        }
        $result->close();
      }
    }

    $field_map_acctg=array();

    $type_array = array('CHECK', 'GENERAL JOURNAL', 'PAYMENT', 'BILL', 'BILL REFUND', 'INVOICE', 'CREDIT MEMO');
    $cat_array = array('trns', 'spl');
    foreach ($type_array as $key=>$value) {
      foreach ($cat_array as $catkey=>$catvalue) {
        $sql = "SELECT * FROM $catvalue WHERE trnstype = '$value'";
        if($result = $mysqli->query($sql)) {
          While ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $field_map_acctg[$value.".".$catvalue] = $row;
          }
          $result->close();
        }
      }
    }
    $mysqli->select_db($this->home_client);
    $field_map = array_merge ($field_map, $field_map_acctg);
    return $field_map;
  }

  function emailResults($recipient){
    $email            = new PHPMailer();
    $email->From      = "testing@home.com";
    $email->FromName  = "Home";
    $email->Subject   = "QBO Activity: ".$this->home_client." ".$this->filename." MySQL ID=".$this->homefile_id;
    $email->AddAddress($recipient);
    if(!empty($this->reports)){
      $email->Body    = "QBO activity resulted in the attached reports (sent from ".$this->host.": ".__FILE__.")";
      foreach($this->reports as $report){
        $email->AddAttachment($report, basename($report));//second parameter is the name used in email
      }
    }
    $email->Send();
  }

  function addReportToEmail($path){
    array_push($this->reports, $path);
  }

  function clearReports(){
    foreach($this->reports as $report){
      unlink($report);
    }
    $this->reports = array();
  }

  ///This function creates a report that shows three fields for vendors and customers:  vend or cust, DisplayName, and the QBO id.  
  ///It queries a client's MySQL database and writes the results to a text file in the client's directory
  function getCustVendMysql() {
    $filepath     = $this->dir . pathinfo($this->filename, PATHINFO_FILENAME) . ".csv";
    $db           = fopen($filepath, 'w');
    $home_client = $this->home_client;
    $dir          = $this->dir;
    $filename     = $this->filename;
    $mysqli       = $this->mysqli;
    $homefile_id = $this->homefile_id;
    $tbls 		    = array('vendor_id'=>'vendors', 'customer_id'=>'customers');
 
    // get vendor and customer data
    foreach ($tbls as $key=>$tbl){
      $ref = substr($tbl, 0, -1) . "_ref";
      $sql = "SELECT trns_cat, account_no, $ref FROM $tbl WHERE homefile_id = '$homefile_id'";
      $result = $mysqli->query($sql);
      if ($mysqli->query($sql) == TRUE){
        while ($row = mysqli_fetch_array($result,MYSQLI_ASSOC)){
          fputcsv($db, $row);	
        }
      }else{
        echo $mysqli->error;
      }
    }
    fclose($db);
    return $filepath;
  }
  
  ///This function creates a report that shows three fields for vendors and customers:  vend or cust, DisplayName, and the QBO id.  
  ///It queries a client's QBO account and writes the results to a text file in the client's directory
  function getCustVendQbo() {
    $filepath     = $this->dir . pathinfo($this->filename, PATHINFO_FILENAME) . ".csv";
    $db           = fopen($filepath, 'w');
    $tbls 		    = array('CUST'=>'Customer', 'VEND'=>'Vendor');
 
    // get vendor and customer data
    foreach ($tbls as $key=>$tbl){
      $skipFirstRow = TRUE;
      $result = $this->qbo_trnx_obj->get($tbl, array('DisplayName', 'Id'));

      foreach($result as $raw_row){
        if($skipFirstRow){
          $skipFirstRow = FALSE;
        }else{
          //Replace the first element, a unique key, with either VEND or CUST
          $row = array_replace($raw_row, array('Key' => $key));

          fputcsv($db, $row);	
        }
      }
    }
    fclose($db);
    return $filepath;
  }

  
  ///This function creates a report that shows the chart of accounts.  
  ///It queries a client's QBO account and writes the results to a text file in the client's directory
  function getChartOfAccounts() {
    $filepath     = $this->dir . "ChartOfAccounts.csv";
    $db           = fopen($filepath, 'w');
 
    //Get the data
    $skipFirstRow = FALSE;//Set to True if the first row is not needed
    $result = $this->qbo_trnx_obj->get_coa();

    foreach($result as $row){
      if($skipFirstRow){
        $skipFirstRow = FALSE;
      }else{
        fputcsv($db, $row);	
      }
    }

    fclose($db);
    return $filepath;
  }
}

?>
