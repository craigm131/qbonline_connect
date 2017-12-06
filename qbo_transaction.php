<?php

///qbo_transaction is a class that defines all of the transaction types that are submitted to QBO, this includes:
//  create_customer
//  create_vendor
//  create_invoice
//  create bill
//  create_payment
//  create_billpayment
//  create_creditmemo
//  create_vendorcredit
//  create_journalentry
//  qb_log
//  update_mysql
//  get_coa
//  get_value
//  get
//  show
//  create_item
//  create_term
//  create_account
//  get_account
//  create_class 

class qbo_transaction{

	/** CLASS VARIABLES **/

  /* CLASS FUNCTIONS */
  function __construct($context, $realm, $mysqli){
    $this->context  = $context;
    $this->realm    = $realm;
    $this->mysqli   = $mysqli;
  }
	
  public function create_customer($arr) {
		$cust_arr 	= $this->get('Customer', array('DisplayName','Id'));//use this array to determine if the customer or vendor already exists in QBO
		$term_arr		= $this->get('Term', array('Name','Id'));

		foreach($arr as $data){	
			$update		= FALSE;
			//get QB's sales term id
			$return = NULL;
			if( !isset($data['sales_term_ref']) || $data['sales_term_ref'] == NULL) { $data['sales_term_ref']='Due on receipt'; }
			$return = $this->get_value($term_arr, 'Name', $data['sales_term_ref'], 'Id');
			if( $return == NULL ){
				//entity doesn't exist so create it
				$return 	= $this->create_term($data['sales_term_ref']);
				$term_arr	= $this->get('Term', array('Name','Id'));
			}
			$data['sales_term_ref'] = $return;

      $CustomerService = new QuickBooks_IPP_Service_Customer();

      $return = NULL;
      $return = $this->get_value($cust_arr, 'DisplayName', $data['display_name'], 'Id');
      if( $return == NULL ){
        //record doesn't exist in QBO so create new one
        $Customer = new QuickBooks_IPP_Object_Customer();
        //echo "\n".basename(__FILE__).": record doesn't exist - creating ".$data['display_name'];
      }else{
        //record exists in QBO so update existing one
        $id = $return;
        $customers 	= $CustomerService->query($this->context, $this->realm, "SELECT * FROM Customer WHERE Id = '$id' ");
        $Customer 	= $customers[0];
        $update		= TRUE;
        //echo "\n".basename(__FILE__).": record exists...updating id ".$id;
      }

      if (isset($data['title']) && $data['title'] !== NULL) { $Customer->setTitle($data['title']); }
      if (isset($data['given_name']) && $data['given_name'] !== NULL) { $Customer->setGivenName( substr($data['given_name'],0,24) ); } //first name
      if (isset($data['middle_name']) && $data['middle_name'] !== NULL) { $Customer->setMiddleName($data['middle_name']); }
      if (isset($data['family_name']) && $data['family_name'] !== NULL) { $Customer->setFamilyName($data['family_name']); } //last name
      if (isset($data['company_name']) && $data['company_name'] !== NULL) { $Customer->setCompanyName($data['company_name']); }
      if (isset($data['display_name']) && $data['display_name'] !== NULL) { $Customer->setDisplayName($data['display_name']); } //. mt_rand(0, 1000));// Use this rand number generator to create two accounts with same name
      if (isset($data['display_name']) && $data['display_name'] !== NULL) { $Customer->setPrintOnCheckName($data['display_name']); }
      if (isset($data['taxable']) && $data['taxable'] !== NULL) { $Customer->setTaxable($data['taxable']); }
      if (isset($data['sales_term_ref']) && $data['sales_term_ref'] !== NULL) { $Customer->setSalesTermRef($data['sales_term_ref']); }
      if (isset($data['note']) && $data['note'] !== NULL) { $Customer->setNotes($data['note']); }
      $name = $data['display_name'];
      
      // Phone #
      if (isset($data['primary_phone']) && $data['primary_phone'] !== NULL) 
      { 
        $PrimaryPhone = new QuickBooks_IPP_Object_PrimaryPhone();
        $PrimaryPhone->setFreeFormNumber($data['primary_phone']); 
        $Customer->setPrimaryPhone($PrimaryPhone);
      }
      if (isset($data['alternate_phone']) && $data['alternate_phone'] !== NULL) 
      { 
        $AlternatePhone = new QuickBooks_IPP_Object_AlternatePhone();
        $AlternatePhone->setFreeFormNumber($data['alternate_phone']); 
        $Customer->setAlternatePhone($AlternatePhone);
      }
      
      // Mobile #
      if (isset($data['mobile']) && $data['mobile'] !== NULL) 
      { 
        $Mobile = new QuickBooks_IPP_Object_Mobile();
        $Mobile->setFreeFormNumber($data['mobile']); 
        $Customer->setMobile($Mobile);
      }
    
        // Fax #
      if (isset($data['fax']) && $data['fax'] !== NULL) 
      { 
        $Fax = new QuickBooks_IPP_Object_Fax();
        $Fax->setFreeFormNumber($data['fax']); 
        $Customer->setFax($Fax);
      }
      
      // Bill address
      if (isset($data['bill_addr_line1']) && $data['bill_addr_line1'] !== NULL) 
      { 
        $BillAddr = new QuickBooks_IPP_Object_BillAddr();
        $BillAddr->setLine1($data['bill_addr_line1']); 
        if (isset($data['bill_addr_line2']) && $data['bill_addr_line2'] !== NULL) { $BillAddr->setLine2($data['bill_addr_line2']); }
        if (isset($data['bill_city']) && $data['bill_city'] !== NULL) { $BillAddr->setCity($data['bill_city']); }
        if (isset($data['bill_state']) && $data['bill_state'] !== NULL) { $BillAddr->setCountrySubDivisionCode($data['bill_state']); }
        if (isset($data['bill_postal_code']) && $data['bill_postal_code'] !== NULL) { $BillAddr->setPostalCode($data['bill_postal_code']); }
        $Customer->setBillAddr($BillAddr);
      }

      // Ship address
      if (isset($data['ship_addr_line1']) && $data['ship_addr_line1'] !== NULL) 
      { 
        $ShipAddr = new QuickBooks_IPP_Object_ShipAddr();
        $ShipAddr->setLine1($data['ship_addr_line1']); 
        if (isset($data['ship_addr_line2']) && $data['ship_addr_line2'] !== NULL) { $ShipAddr->setLine2($data['ship_addr_line2']); }
        if (isset($data['ship_city']) && $data['ship_city'] !== NULL) { $ShipAddr->setCity($data['ship_city']); }
        if (isset($data['ship_state']) && $data['ship_state'] !== NULL) { $ShipAddr->setCountrySubDivisionCode($data['ship_state']); }
        if (isset($data['ship_postal_code']) && $data['ship_postal_code'] !== NULL) { $ShipAddr->setPostalCode($data['ship_postal_code']); }
        $Customer->setShipAddr($ShipAddr);
      }

      // Email
      if (isset($data['primary_email']) && $data['primary_email'] !== NULL && filter_var($data['primary_email'], FILTER_VALIDATE_EMAIL) !== FALSE) 
      { 
        $PrimaryEmailAddr = new QuickBooks_IPP_Object_PrimaryEmailAddr();
        $PrimaryEmailAddr->setAddress($data['primary_email']); 
        $Customer->setPrimaryEmailAddr($PrimaryEmailAddr);
      }
      
      if($update)
      {
        //Update exiting record
        if($resp = $CustomerService->update($this->context, $this->realm, $id, $Customer))
        {
          $log = array(TRUE, $id, $name, '');
        }
        else
        {	
          $resp 	= $CustomerService->lastError($this->context);
          $log 	= array(FALSE, $resp, $name, '');
        }
      }
      else
      {
        //Record doesn't exist so add to QBO
        if ($resp = $CustomerService->add($this->context, $this->realm, $Customer))
        {
          $log = array(TRUE, $resp, $name, '');
        }
        else
        {	
          $resp 	= $CustomerService->lastError($this->context);
          $log 	= array(FALSE, $resp, $name, '');
        }
      }
      $this->qb_log($log, 'customer');
		}
		return;
  }

	public function create_vendor($arr) {
		$vend_arr = $this->get('Vendor', array('DisplayName','Id'));//use this array to determine if the customer or vendor already exists in QBO
		$term_arr	= $this->get('Term', array('Name','Id'));

		foreach($arr as $data){	
			$update	= FALSE;
			//get QB's sales term id
			$return = NULL;
			if( !isset($data['sales_term_ref']) || $data['sales_term_ref'] == NULL) { $data['sales_term_ref']='Due on receipt'; }
			$return = $this->get_value($term_arr, 'Name', $data['sales_term_ref'], 'Id');
			if( $return == NULL ){
				//entity doesn't exist so create it
				$return 	= $this->create_term($data['sales_term_ref']);
				$term_arr	= $this->get('Term', array('Name','Id'));
			}
			$data['sales_term_ref'] = $return;

      $VendorService = new QuickBooks_IPP_Service_Vendor();

      $return = NULL;
      $return = $this->get_value($vend_arr, 'DisplayName', $data['display_name'], 'Id');
      if( $return == NULL ){
        //record doesn't exist in QBO so create new one
        $Vendor = new QuickBooks_IPP_Object_Vendor();
        //echo "\n".basename(__FILE__).": record doesn't exist - creating ".$data['display_name'];
      }else{
        //record exists in QBO so update existing one
        $id 		  = $return;
        $vendors 	= $VendorService->query($this->context, $this->realm, "SELECT * FROM Vendor WHERE Id = '$id' ");
        $Vendor 	= $vendors[0];
        $update		= TRUE;
        //echo "\n".basename(__FILE__).": record exists...updating id ".$id;
      }
      if (isset($data['title']) && $data['title'] !== NULL) { $Vendor->setTitle($data['title']); }
      if (isset($data['given_name']) && $data['given_name'] !== NULL) { $Vendor->setGivenName( substr($data['given_name'],0,24) ); }//QBO limits to 25 characters
      if (isset($data['middle_name']) && $data['middle_name'] !== NULL) { $Vendor->setMiddleName($data['middle_name']); }
      if (isset($data['family_name']) && $data['family_name'] !== NULL) { $Vendor->setFamilyName($data['family_name']); }
      if (isset($data['company_name']) && $data['company_name'] !== NULL) { $Vendor->setCompanyName($data['company_name']); }
      if (isset($data['display_name']) && $data['display_name'] !== NULL) { $Vendor->setDisplayName($data['display_name']); }//. mt_rand(0, 1000));
      if (isset($data['print_name']) && $data['print_name'] !== NULL) { $Vendor->setPrintOnCheckName($data['print_name']); }
      if (isset($data['sales_term_ref']) && $data['sales_term_ref'] !== NULL) { $Vendor->setTermRef($data['sales_term_ref']); }
      if (isset($data['tax_id']) && $data['tax_id'] !== NULL) { $Vendor->setTaxIdentifier($data['tax_id']); }
      if (isset($data['vendor_1099']) && $data['vendor_1099'] !== NULL) { $Vendor->setVendor1099($data['vendor_1099']); }
      if (isset($data['note']) && $data['note'] !== NULL) { $Vendor->setNotes($data['note']); }
      $name = $data['display_name'];
      
      if (isset($data['bill_addr_line1']) && $data['bill_addr_line1'] !== NULL) {
        $BillAddr = new QuickBooks_IPP_Object_BillAddr();
        $BillAddr->setLine1($data['bill_addr_line1']);
        if (isset($data['bill_addr_line2']) && $data['bill_addr_line2'] !== NULL) { $BillAddr->setLine2($data['bill_addr_line2']); }
        if (isset($data['bill_city']) && $data['bill_city'] !== NULL) { $BillAddr->setCity($data['bill_city']); }
        if (isset($data['bill_state']) && $data['bill_state'] !== NULL) { $BillAddr->setCountrySubDivisionCode($data['bill_state']); }
        if (isset($data['bill_postal_code']) && $data['bill_postal_code'] !== NULL) { $BillAddr->setPostalCode($data['bill_postal_code']); }
        $Vendor->setBillAddr($BillAddr);
      }
      
      if (isset($data['primary_email']) && $data['primary_email'] !== NULL && filter_var($data['primary_email'], FILTER_VALIDATE_EMAIL) !== FALSE){ 
        $PrimaryEmailAddr = new QuickBooks_IPP_Object_PrimaryEmailAddr();
        $PrimaryEmailAddr->setAddress($data['primary_email']); 
        $Vendor->setPrimaryEmailAddr($PrimaryEmailAddr);
      }

      if (isset($data['primary_phone']) && $data['primary_phone'] !== NULL) { 
        $PrimaryPhone = new QuickBooks_IPP_Object_PrimaryPhone();
        $PrimaryPhone->setFreeFormNumber($data['primary_phone']); 
        $Vendor->setPrimaryPhone($PrimaryPhone);
      }

      if (isset($data['alternate_phone']) && $data['alternate_phone'] !== NULL) { 
        $AlternatePhone = new QuickBooks_IPP_Object_AlternatePhone();
        $AlternatePhone->setFreeFormNumber($data['alternate_phone']);
        $Vendor->setAlternatePhone($AlternatePhone); 
      }

      if (isset($data['fax']) && $data['fax'] !== NULL) { 
        $Fax = new QuickBooks_IPP_Object_Fax();
        $Fax->setFreeFormNumber($data['fax']); 
        $Vendor->setFax($Fax);
      }
      
      if($update){
        //Update existing record
        if($resp = $VendorService->update($this->context, $this->realm, $id, $Vendor)){
          $log = array(TRUE, $id, $name, '');
        }else{	
          $resp 	= $VendorService->lastError($this->context);
          $log 	= array(FALSE, $resp, $name, '');
        }
      }else{
        //Record doesn't exist so add to QBO
        if ($resp = $VendorService->add($this->context, $this->realm, $Vendor)){
          $log 	= array(TRUE, $resp, $name, '');
        }else{	
          //echo "\nNot recorded";
          $resp = $VendorService->lastError($this->context);
          $log 	= array(FALSE, $resp, $name, '');
        }
      }
      $this->qb_log($log, 'vendor');
		}
		return;
  }

	public function create_invoice($arr){
    $i=1;
		//echo "\nGetting accounts";
		$acct_arr		= $this->get('Account', array('FullyQualifiedName', 'AccountSubType', 'Id'));
		//echo "\nGetting terms";
		$term_arr		= $this->get('Term', array('Name', 'Id'));
		//echo "\nGetting items";
		$item_arr		= $this->get('Item', array('FullyQualifiedName', 'Id'));
		//echo "\nGetting classes";
		$class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
		
		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before entering into QBO
			//echo "\nGetting data before validation ". $i++;
			foreach ($v1['trns'] as $k2=>$data){
				//get QB's sales term id
				$return = NULL;
				$return = $this->get_value($term_arr, 'Name', $data['sales_term_ref'], 'Id');
				if( $return == NULL ){
					//entity doesn't exist so create it
					$return 	= $this->create_term($data['sales_term_ref']);
					$term_arr	= $this->get('Term', array('Name','Id'));
				}
				$v1['trns'][$k2]['sales_term_ref'] = $return;
      }
      foreach ($v1['spl'] as $k2=>$data){
        //get QB's item id
        $return = NULL;
        $return = $this->get_value($item_arr, 'FullyQualifiedName', $data['item'], 'Id');
        if( $return == NULL ){
          //entity doesn't exist so create it and update reference array
          $return   = $this->create_item($data['item'], $data['accnt'], $acct_arr);
          $item_arr	= $this->get('Item', array('FullyQualifiedName','Id'));
        }
        $v1['spl'][$k2]['item_ref'] = $return; 

        //get QB's class id
        if( isset($data['class']) ){
          $return = NULL;
          $return = $this->get_value($class_arr, 'FullyQualifiedName', $data['class'], 'Id');
          if ( $return == NULL ){
            //entity doesn't exist so create it and update reference array
            $return     = $this->create_class($data['class'], $class_arr);
            $class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
          }
          $v1['spl'][$k2]['class'] = $return;
        }
      }

      //echo "\nWriting data";
      foreach ($v1['trns'] as $k2=>$data){
        $InvoiceService = new QuickBooks_IPP_Service_Invoice();
        $Invoice = new QuickBooks_IPP_Object_Invoice();
        if (isset($data['doc_number']) && $data['doc_number'] !== NULL) { $Invoice->setDocNumber($data['doc_number']); }//must be unique
        if (isset($data['txn_date']) && $data['txn_date'] !== NULL) { $Invoice->setTxnDate($data['txn_date']); }
        if (isset($data['due_date']) && $data['due_date'] !== NULL) { $Invoice->setDueDate($data['due_date']); }
        if (isset($data['ship_date']) && $data['ship_date'] !== NULL) { $Invoice->setShipDate($data['ship_date']); }
        if (isset($data['customer_memo']) && $data['customer_memo'] !== NULL) { $Invoice->setCustomerMemo($data['customer_memo']); }
        if (isset($data['sales_term_ref']) && $data['sales_term_ref'] !== NULL) { $Invoice->setSalesTermRef($data['sales_term_ref']); }
        //if (isset($data['customer_ref']) && $data['customer_ref'] !== NULL) { $Invoice->setCustomerRef($data['customer_ref']); }  // Exclude this line if id is provided in datafile
        if (isset($data['name']) && $data['name'] !== NULL) { $Invoice->setCustomerRef($data['name']); } // Use this line if id is provided in datafile
        if (isset($data['print_status']) && $data['print_status'] !== NULL) { $Invoice->setPrintStatus($data['print_status']); }
        if (isset($data['private_note']) && $data['private_note'] !== NULL) { $Invoice->setPrivateNote($data['private_note']); }
        $name = $data['name'];

        // Ship address
        if (isset($data['ship_addr1']) && $data['ship_addr1'] !== NULL) 
        { 
          $ShipAddr = new QuickBooks_IPP_Object_ShipAddr();
          $ShipAddr->setLine1($data['ship_addr1']); 
          if (isset($data['ship_addr2']) && $data['ship_addr2'] !== NULL) { $ShipAddr->setLine2($data['ship_addr2']); }
          if (isset($data['ship_addr3']) && $data['ship_addr3'] !== NULL) { $ShipAddr->setLine3($data['ship_addr3']); }
            $Invoice->setShipAddr($ShipAddr);
        }
      }
      foreach ($v1['spl'] as $k2=>$data){	
        $Line = new QuickBooks_IPP_Object_Line();
        $Line->setDetailType('SalesItemLineDetail');
        $Line->setAmount($data['amount']);
        if (isset($data['description']) && $data['description'] !== NULL) { $Line->setDescription($data['description']); }

        $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
        if (isset($data['item_ref']) && $data['item_ref'] !== NULL) { $SalesItemLineDetail->setItemRef($data['item_ref']); }
        if (isset($data['class']) && $data['class'] !== NULL) { $SalesItemLineDetail->setClassRef($data['class']); }
        if (isset($data['unit_price']) && $data['unit_price'] !== NULL) { $SalesItemLineDetail->setUnitPrice($data['unit_price']); }
        if (isset($data['qty']) && $data['qty'] !== NULL) { $SalesItemLineDetail->setQty($data['qty']); }

        $Line->addSalesItemLineDetail($SalesItemLineDetail);

        $Invoice->addLine($Line);
      }
      if ($resp = $InvoiceService->add($this->context, $this->realm, $Invoice)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }
      else{
        $resp = $InvoiceService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }	
    return;
  }

	public function create_bill($arr){
		$acct_arr	  = $this->get('Account', array('FullyQualifiedName', 'FullyQualifiedName', 'AccountSubType', 'Id'));
		$term_arr	  = $this->get('Term', array('Name', 'Name', 'Id'));
		$class_arr	= $this->get('Class', array('FullyQualifiedName', 'FullyQualifiedName', 'Id'));

		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before validation
			foreach ($v1['trns'] as $k2=>$data){
				//get QB's sales term id
				$return = NULL;
				$return = $this->get_value($term_arr, 'Name', $data['sales_term_ref'], 'Id');
				if( $return == NULL ){
					//entity doesn't exist so create it
					$return 	= $this->create_term($data['sales_term_ref']);
					$term_arr	= $this->get('Term', array('Name','Id'));
				}
				$v1['trns'][$k2]['sales_term_ref'] = $return;
      }
      foreach ($v1['spl'] as $k2=>$data){
        //get QB's class id
        if( isset($data['class']) ){
          $return = NULL;
          $return = $this->get_value($class_arr, 'FullyQualifiedName', $data['class'], 'Id');
          if ( $return == NULL ){
            //entity doesn't exist so create it and update reference array
            $return     = $this->create_class($data['class'], $class_arr);
            $class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
          }
          $v1['spl'][$k2]['class'] = $return;
        }
      }
      foreach ($v1['trns'] as $k2=>$data){	
        $BillService = new QuickBooks_IPP_Service_Bill();
        $Bill = new QuickBooks_IPP_Object_Bill();
        if (isset($data['doc_number']) && $data['doc_number'] 		!== NULL) { $Bill->setDocNumber($data['doc_number']); }//optional
        if (isset($data['private_note']) && $data['private_note'] !== NULL) { $Bill->setPrivateNote($data['private_note']); }
        if (isset($data['txn_date']) && $data['txn_date'] 				!== NULL) { $Bill->setTxnDate($data['txn_date']); }//optional
        if (isset($data['due_date']) && $data['due_date'] 				!== NULL) { $Bill->setDueDate($data['due_date']); }//optional
        //if (isset($data['vendor_ref']) && $data['vendor_ref'] 			!== NULL) { $Bill->setVendorRef($data['vendor_ref']); }// exclude if id is included in datafile
        if (isset($data['name']) && $data['name'] 						    !== NULL) { $Bill->setVendorRef($data['name']); }// use if id included in datafile
        if (isset($data['sales_term_ref']) && $data['sales_term_ref'] 	!== NULL) { $Bill->setSalesTermRef($data['sales_term_ref']); }
        if (isset($data['accnt']) && $data['accnt'] 	            !== NULL) { $Bill->setAPAccountRef($data['accnt']); }
        $name = $data['name'];
      }
      foreach ($v1['spl'] as $k2=>$data){	
        $Line = new QuickBooks_IPP_Object_Line();
        if (isset($data['description']) && $data['description']   !== NULL) { $Line->setDescription(($data['description'])); }
        if (isset($data['class']) && $data['class']               !== NULL) { $Line->setClassRef(($data['class'])); }
        if (isset($data['amount']) && $data['amount']             !== NULL) { $Line->setAmount($data['amount']); }//required
        $Line->setDetailType('AccountBasedExpenseLineDetail');//required.  eg 'AccountBasedExpenseLineDetail'

        $AccountBasedExpenseLineDetail = new QuickBooks_IPP_Object_AccountBasedExpenseLineDetail();
        if (isset($data['accnt']) && $data['accnt'] !== NULL) { $AccountBasedExpenseLineDetail->setAccountRef($data['accnt']); }

        $Line->setAccountBasedExpenseLineDetail($AccountBasedExpenseLineDetail);

        $Bill->addLine($Line);
      }

      if ($resp = $BillService->add($this->context, $this->realm, $Bill)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }
      else{
        $resp = $BillService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }	
    return;
  }

	public function create_payment($arr){
		$acct_arr		  = $this->get('Account', array('FullyQualifiedName', 'AccountSubType', 'Id'));
		$invoice_arr	= $this->get('Invoice', array('Id', 'DocNumber'));
		$paymeth_arr	= $this->get('PaymentMethod', array('Id', 'Name'));

		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before validation
			foreach ($v1['trns'] as $k2=>$data){
      }
      foreach ($v1['spl'] as $k2=>$data){
        //get the QB invoice id from QB
        $return = NULL;
        if(isset($data['doc_number']))
          $return = $this->get_value($invoice_arr, 'DocNumber', $data['doc_number'], 'Id');
        $v1['spl'][$k2]['txn_id'] = $return;
      }
      foreach ($v1['trns'] as $k2=>$data){	
        $PaymentService = new QuickBooks_IPP_Service_Payment();
        // Create payment object
        $Payment = new QuickBooks_IPP_Object_Payment();
        if (isset($data['doc_number']) && $data['doc_number'] !== NULL) { $Payment->setPaymentRefNum($data['doc_number']); }
        if (isset($data['memo']) && $data['memo'] !== NULL) { $Payment->setPrivateNote($data['memo']); }
        if (isset($data['txn_date']) && $data['txn_date'] !== NULL) { $Payment->setTxnDate($data['txn_date']); }
        if (isset($data['amount']) && $data['amount'] !== NULL) { $Payment->setTotalAmt($data['amount']); }
        if (isset($data['name']) && $data['name'] !== NULL) { $Payment->setCustomerRef($data['name']); } //use if id is provided in datafile
        //if (isset($data['customer_ref']) && $data['customer_ref'] !== NULL) { $Payment->setCustomerRef($data['customer_ref']); } //use if id is NOT provided in datafile
        if (isset($data['accnt']) && $data['accnt'] !== NULL) { $Payment->setDepositToAccountRef($data['accnt']); }
        $name = $data['name'];
      }
      foreach ($v1['spl'] as $k2=>$data){	
        // Create line for payment (this details what it's applied to)
        $Line = new QuickBooks_IPP_Object_Line();
        if (isset($data['amount']) && $data['amount'] !== NULL) { $Line->setAmount($data['amount']); } //amount must be positive

        // The line has a LinkedTxn node which links to the actual invoice
        $LinkedTxn = new QuickBooks_IPP_Object_LinkedTxn();
        if (isset($data['txn_id']) && $data['txn_id'] !== NULL) { $LinkedTxn->setTxnId($data['txn_id']); } //eg 226
        $LinkedTxn->setTxnType('Invoice');

        $Line->setLinkedTxn($LinkedTxn);

        $Payment->addLine($Line);
      }

      if ($resp = $PaymentService->add($this->context, $this->realm, $Payment)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }
      else{
        $resp = $PaymentService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }	
    return;
  }

	public function create_billpayment($arr) {
		$data['pay_type'] = 'Check';
		$acct_arr		= $this->get('Account', array('FullyQualifiedName', 'Id'));
		$term_arr		= $this->get('Term', array('Name', 'Name', 'Id'));
		$item_arr		= $this->get('Item', array('FullyQualifiedName', 'Id'));
		$class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
		$bill_arr		= $this->get('Bill', array('Id', 'DocNumber'));

		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before validation
			$memo_string = NULL;
			foreach ($v1['trns'] as $k2=>$data){
			}
			foreach ($v1['spl'] as $k2=>$data){
				//get the bill id from QB
				$return = NULL;
				$return = $this->get_value($bill_arr, 'DocNumber', $data['doc_number'], 'Id');
				$v1['spl'][$k2]['txn_id'] = $return;//the bill that is being paid, eg '341'
			}

      foreach ($v1['trns'] as $k2=>$data){
        $BillPaymentService = new QuickBooks_IPP_Service_BillPayment();
        // Create bill payment object
        $BillPayment = new QuickBooks_IPP_Object_BillPayment();
        if (isset($data['doc_number']) && $data['doc_number'] 		!== NULL) { $BillPayment->setDocNumber($data['doc_number']); }
        if (isset($data['txn_date']) && $data['txn_date'] 			!== NULL) { $BillPayment->setTxnDate($data['txn_date']); }
        if (isset($data['amount']) && $data['amount'] 				!== NULL) { $BillPayment->setTotalAmt(($data['amount'])); }

        $BillPayment->setPayType('Check');//only Check is used (no credit card)
        if (isset($data['name']) && $data['name'] 		!== NULL) { $BillPayment->setVendorRef($data['name']); } //use if id is provided in datafile
        //if (isset($data['vendor_ref']) && $data['vendor_ref'] 		!== NULL) { $BillPayment->setVendorRef($data['vendor_ref']); } //use if id is NOT provided in datafile
        //if (isset($data['accnt']) && $data['accnt'] 	!== NULL) { $BillPayment->setAPAccountRef($data['accnt']); }//QBO ignores this and used the bill's A/P account
        $BillPayment->setCheckPayment('CheckPayment');

        $CheckPayment = new QuickBooks_IPP_Object_CheckPayment();
        if (isset($data['accnt']) && $data['accnt'] !== NULL) {$CheckPayment->setBankAccountRef($data['accnt']); }//changed to accnt tcm020915
        if (isset($data['print_status']) && $data['print_status'] 	!== NULL) {$CheckPayment->setPrintStatus($data['print_status']); }//eg 'NotSet'
        $CheckPayment->setPrintStatus('NotSet');
        $BillPayment->addLine($CheckPayment);// SUCCESS - must have this line!!!
        $name = $data['name'];
      }
      foreach ($v1['spl'] as $k2=>$data){
        if (isset($data['doc_number']) && isset($data['memo'])){
          if($memo_string == NULL){
            $memo_string = $data['doc_number'].":".$data['memo']."; ";
          }else{
            $memo_string .= $data['doc_number'].":".$data['memo']."; ";
          }
        }
        if (isset($data['memo']) && $data['memo'] 					!== NULL) { $BillPayment->setPrivateNote($memo_string); }

        // Create line for payment (this details to what it's applied)
        $Line = new QuickBooks_IPP_Object_Line();
        if (isset($data['amount']) && $data['amount'] 				!== NULL) { $Line->setAmount(($data['amount'])); }
        //if (isset($data['memo']) && $data['memo'] 					!== NULL) { $Line->setDescription(($data['memo'])); }

        // The line has a LinkedTxn node which links to the actual vendor bill
        $LinkedTxn = new QuickBooks_IPP_Object_LinkedTxn();
        if (isset($data['txn_id']) && $data['txn_id'] 				!== NULL) { $LinkedTxn->setTxnId($data['txn_id']); } //eg 341
        $LinkedTxn->setTxnType('Bill');

        $Line->setLinkedTxn($LinkedTxn);

        $BillPayment->addLine($Line);
      }

      if ($resp = $BillPaymentService->add($this->context, $this->realm, $BillPayment)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }else{
        $resp = $BillPaymentService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }
    return;
  }

	public function create_creditmemo($arr) {
		$acct_arr		= $this->get('Account', array('FullyQualifiedName', 'AccountSubType', 'Id'));
		$item_arr		= $this->get('Item', array('FullyQualifiedName', 'Id'));
		$class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));

    foreach ($arr as $acctg_id=>$v1){
      // enter QB data related to this transaction type before validation
      foreach ($v1['trns'] as $k2=>$data){
        //...
      }
      foreach ($v1['spl'] as $k2=>$data){
        //get QB's item id
        $return = NULL;
        $return = $this->get_value($item_arr, 'FullyQualifiedName', $data['item'], 'Id');
        if( $return == NULL ){
          //entity doesn't exist so create it and update reference array
          $return   = $this->create_item($data['item'], $data['accnt'], $acct_arr);
          $item_arr = $this->get('Item', array('FullyQualifiedName','Id'));
        }
        $v1['spl'][$k2]['item'] = $return; 

        //get QB's class id
        if( isset($data['class']) ){
          $return = NULL;
          $return = $this->get_value($class_arr, 'FullyQualifiedName', $data['class'], 'Id');
          if ( $return == NULL ){
            //entity doesn't exist so create it and update reference array
            $return     = $this->create_class($data['class'], $class_arr);
            $class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
          }
          $v1['spl'][$k2]['class'] = $return;
        }
      }
      foreach ($v1['trns'] as $k2=>$data){
        $CreditMemoService = new QuickBooks_IPP_Service_CreditMemo();

        $CreditMemo = new QuickBooks_IPP_Object_CreditMemo();
        if (isset($data['txn_date']) && $data['txn_date'] 		!== NULL) { $CreditMemo->setTxnDate('txn_date'); }//Optional.  If not entered, the server's current date is used.
        if (isset($data['name']) && $data['name'] 				!== NULL) { $CreditMemo->setCustomerRef($data['name']); } //Use if id is provided in datafile
        if (isset($data['doc_number']) && $data['doc_number'] 	!== NULL) { $CreditMemo->setDocNumber($data['doc_number']); }
        //if (isset($data['customer_ref']) && $data['customer_ref'] !== NULL) { $CreditMemo->setCustomerRef($data['customer_ref']); }//Required.  Use if Id is NOT provided in datafile.
        // This is the accounts receivable account related to the customer.  The credit is applied here.
        if (isset($data['private_note']) && $data['private_note'] !== NULL) { $CreditMemo->setPrivateNote($data['private_note']); }//Optional. This is a note that maps to the Memo field.  The customer does not see this.
        $name = $data['name'];
      }
      foreach ($v1['spl'] as $k2=>$data){
        $Line = new QuickBooks_IPP_Object_Line();
        if (isset($data['qty']) && $data['qty'] !== NULL && isset($data['unit_price']) && $data['unit_price'] !== NULL){
          $data['amount'] = $data['qty'] * $data['unit_price'];
        }
        if (isset($data['amount']) && $data['amount'] !== NULL) { $Line->setAmount(($data['amount'])); }//Required
        if (isset($data['class']) && $data['class'] !== NULL) { $CreditMemo->setClassRef($data['class']); }
        if (isset($data['description']) && $data['description'] !== NULL) { $Line->setDescription($data['description']); }//Optional.  This is the credit memo description that the customer sees.
        $Line->setDetailType('SalesItemLineDetail');

        $SalesItemLineDetail = new QuickBooks_IPP_Object_SalesItemLineDetail();
        if (isset($data['item']) && $data['item'] 	!== NULL) { $SalesItemLineDetail->setItemRef($data['item']); }//Required.  This account is debited (e.g. "1" is the item, "Sales").
        if (isset($data['qty']) && $data['qty'] 	!== NULL) { $SalesItemLineDetail->setQty(($data['qty'])); }
        if (isset($data['unit_price']) && $data['unit_price'] !== NULL) { $SalesItemLineDetail->setUnitPrice(($data['unit_price'])); }

        $Line->setSalesItemLineDetail($SalesItemLineDetail);

        $CreditMemo->addLine($Line);
      }

      if ($resp = $CreditMemoService->add($this->context, $this->realm, $CreditMemo)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }
      else{
        $resp = $CreditMemoService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }  
    return;
  }

	public function create_vendorcredit($arr){
		$acct_arr		= $this->get('Account', array('FullyQualifiedName', 'AccountSubType', 'Id'));

		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before validation
			foreach ($v1['trns'] as $k2=>$data){
			}
			foreach ($v1['spl'] as $k2=>$data){
			}
      foreach ($v1['trns'] as $k2=>$data){
        $VendorCreditService = new QuickBooks_IPP_Service_VendorCredit();

        $VendorCredit = new QuickBooks_IPP_Object_VendorCredit();

        if (isset($data['txn_date']) && $data['txn_date'] !== NULL) { $VendorCredit->setTxnDate('txn_date'); }//Optional.  If not entered, the server's current date is used.
        if (isset($data['name']) && $data['name'] !== NULL) { $VendorCredit->setVendorRef($data['name']); }//Use if id provided in datafile
        //if (isset($data['vendor_ref']) && $data['vendor_ref'] !== NULL) { $VendorCredit->setVendorRef($data['vendor_ref']); }//Use if id NOT provided in datafile
        if (isset($data['private_note']) && $data['private_note'] !== NULL) { $VendorCredit->setPrivateNote($data['private_note']); }//Optional. This is a note that maps to the Memo field.  The vendor does not see this.
        if (isset($data['accnt']) && $data['accnt'] !== NULL) { $VendorCredit->setAPAccountRef($data['accnt']); }
        if (isset($data['doc_number']) && $data['doc_number'] !== NULL) { $VendorCredit->setDocNumber($data['doc_number']); }
        $name = $data['name'];
      }
      foreach ($v1['spl'] as $k2=>$data){
        $Line = new QuickBooks_IPP_Object_Line();
        if (isset($data['amount']) && $data['amount'] !== NULL) { $Line->setAmount(($data['amount'])); }//Required
        if (isset($data['description']) && $data['description'] !== NULL) {$Line->setDescription($data['description']); }//Optional.  This is the credit memo description that the vendor sees.
        $Line->setDetailType('AccountBasedExpenseLineDetail');

        $AccountBasedExpenseLineDetail = new QuickBooks_IPP_Object_AccountBasedExpenseLineDetail();
        if (isset($data['accnt']) && $data['accnt'] !== NULL) { $AccountBasedExpenseLineDetail->setAccountRef($data['accnt']); }//default to expense account '39' ('Freight & Delivery' expense)

        $Line->setAccountBasedExpenseLineDetail($AccountBasedExpenseLineDetail);

        $VendorCredit->addLine($Line);
      }

      if ($resp = $VendorCreditService->add($this->context, $this->realm, $VendorCredit)){
        $log 	= array(TRUE, $resp, $name, $acctg_id);
      }
      else{
        $resp = $VendorCreditService->lastError($this->context);
        $log 	= array(FALSE, $resp, $name, $acctg_id);
      }
      $this->qb_log($log, '');
    }	
    return;
  }

  public function create_journalentry($arr){
    $mysqli     = $this->mysqli;
		$acct_arr		= $this->get('Account', array('FullyQualifiedName', 'AccountSubType', 'Id', 'AccountType'));
		$class_arr	= $this->get('Class', array('FullyQualifiedName', 'FullyQualifiedName', 'Id'));

		foreach ($arr as $acctg_id=>$v1){
			// enter QB data related to this transaction type before validation
			foreach ($v1['trns'] as $k2=>$data){
				if(!isset($account_type)){
					$account_type = $this->get_value($acct_arr, 'Id', $v1['trns'][$k2]['accnt'], 'AccountType');
				}

				if($account_type == 'Accounts Payable'){
					$v1['trns'][$k2]['entity'] 		= 'Vendor';
					$v1['trns'][$k2]['entity_ref'] 	= $v1['trns'][$k2]['name'];
				}
				elseif($account_type == 'Accounts Receivable'){
					$v1['trns'][$k2]['entity'] 		= 'Customer';
					$v1['trns'][$k2]['entity_ref'] 	= $v1['trns'][$k2]['name'];
				}

				//get QB's class id
				if( isset($data['class']) ){
					$return = NULL;
					$return = $this->get_value($class_arr, 'FullyQualifiedName', $data['class'], 'Id');
					if ( $return == NULL ){
						//entity doesn't exist so create it and update reference array
						$return     = $this->create_class($data['class'], $class_arr);
						$class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
					}
					$v1['trns'][$k2]['class'] = $return;
				}
			}
			foreach ($v1['spl'] as $k2=>$data){
				if(!isset($account_type)){
					$account_type = $this->get_value($acct_arr, 'Id', $v1['spl'][$k2]['accnt'], 'AccountType');
				}

				if($account_type == 'Accounts Payable'){
					$v1['spl'][$k2]['entity'] 		= 'Vendor';
					$v1['spl'][$k2]['entity_ref'] 	= $v1['spl'][$k2]['name'];
				}
				elseif($account_type == 'Accounts Receivable'){
					$v1['spl'][$k2]['entity'] 		= 'Customer';
					$v1['spl'][$k2]['entity_ref'] 	= $v1['spl'][$k2]['name'];
				}

				//get QB's class id
				if( isset($data['class']) ){
					$return = NULL;
					$return = $this->get_value($class_arr, 'FullyQualifiedName', $data['class'], 'Id');
					if ( $return == NULL ){
						//entity doesn't exist so create it and update reference array
						$return     = $this->create_class($data['class'], $class_arr);
						$class_arr	= $this->get('Class', array('FullyQualifiedName', 'Id'));
					}
					$v1['spl'][$k2]['class'] = $return;
				}
			}
			foreach ($v1['trns'] as $k2=>$data){
				$JournalEntryService = new QuickBooks_IPP_Service_JournalEntry();
					
				// Main journal entry object
				$JournalEntry = new QuickBooks_IPP_Object_JournalEntry();
				if (isset($data['doc_number']) && $data['doc_number'] !== NULL) { $JournalEntry->setDocNumber($data['doc_number']); }
				if (isset($data['txn_date']) && $data['txn_date'] !== NULL) { $JournalEntry->setTxnDate($data['txn_date']); }
				if (isset($data['private_note']) && $data['private_note'] !== NULL) { $JournalEntry->setPrivateNote($data['private_note']); }

				// Debit line
				$Line1 = new QuickBooks_IPP_Object_Line();
				if (isset($data['memo']) && $data['memo'] !== NULL) { $Line1->setDescription($data['memo']); }
				if (isset($data['amount']) && $data['amount'] !== NULL) { $Line1->setAmount(($data['amount'])); }

				$Line1->setDetailType('JournalEntryLineDetail');
					$Detail1 = new QuickBooks_IPP_Object_JournalEntryLineDetail();
					$Detail1->setPostingType('Debit');
					if (isset($data['accnt']) && $data['accnt'] !== NULL) { $Detail1->setAccountRef($data['accnt']); }//required. debit account eg 3
					if (isset($data['class']) && $data['class'] !== NULL) { $Detail1->setClassRef($data['class']); }
						$Entity1 = new QuickBooks_IPP_Object_Entity();
						if (isset($data['entity']) && $data['entity'] !== NULL) { $Entity1->setType($data['entity']); }
						if (isset($data['entity_ref']) && $data['entity_ref'] !== NULL) { $Entity1->setEntityRef($data['entity_ref']); }
					
				$name = NULL;
				if ( isset($data['name']) ) { $name = $data['name']; }
					
				$Detail1->addEntity($Entity1);
				$Line1->addJournalEntryLineDetail($Detail1);
				$JournalEntry->addLine($Line1);
			}
			foreach ($v1['spl'] as $k2=>$data){
				// Credit line
				$Line2 = new QuickBooks_IPP_Object_Line();
				if (isset($data['memo']) && $data['memo'] !== NULL) { $Line2->setDescription($data['memo']); }
				if (isset($data['amount']) && $data['amount'] !== NULL) { $Line2->setAmount(($data['amount'])); }

				$Line2->setDetailType('JournalEntryLineDetail');
					$Detail2 = new QuickBooks_IPP_Object_JournalEntryLineDetail();
					$Detail2->setPostingType('Credit');
					if (isset($data['accnt']) && $data['accnt'] 			!== NULL) { $Detail2->setAccountRef($data['accnt']); }//required. eg 56
					if (isset($data['class']) && $data['class'] 			!== NULL) { $Detail2->setClassRef($data['class']); }
						$Entity2 = new QuickBooks_IPP_Object_Entity();
						if (isset($data['entity']) && $data['entity'] !== NULL) { $Entity2->setType($data['entity']); }
						if (isset($data['entity_ref']) && $data['entity_ref'] 	!== NULL) { $Entity2->setEntityRef($data['entity_ref']); }
					
				$Detail2->addEntity($Entity2);
				$Line2->addJournalEntryLineDetail($Detail2);
				$JournalEntry->addLine($Line2);
			}

			if ($resp = $JournalEntryService->add($this->context, $this->realm, $JournalEntry)){
				$log 	= array(TRUE, $resp, $name, $acctg_id);
			}else{
				$resp = $JournalEntryService->lastError($this->context);
				$log 	= array(FALSE, $resp, $name, $acctg_id);
			}
		  $this->qb_log($log, '');
	  }	
    return;
  }

  function qb_log($log, $type){
    $mysqli = $this->mysqli;
    
    //$log[0] is TRUE or FALSE depending on whether the record was written to QBO
    $resp 		= $log[1];	//the message returned by QBO
    $name 		= $log[2];	//the name, or id, of the related customer/vendor
    $acctg_id 	= $log[3];	//the unique MySQL id of the acctg record

    $resp = $mysqli->real_escape_string($resp);
    $name = $mysqli->real_escape_string($name);

    if($type == 'customer' || $type == 'vendor')
    {
      //log for customer and vendor
      // check to see if record was copied to QBO
      if($log[0] == TRUE) {
        // Log success
        // Update MySQL record (table, set field, set value, where field, where value, connection, path to datafile, datafile name)
        $resp = str_replace(array('[', ']', '{', '}', '-'), '', $resp);
        $this->update_mysql($type . 's', 'qb_val'				, 'RECORDED', 							'account_no', $name); // record QB val
        $this->update_mysql($type . 's', 'qb_note'				, 'Our new ID is: [' . $resp . ']', 	'account_no', $name); // record QB note
        $this->update_mysql($type . 's', 'qb_timestamp'		, date('Y-m-d H:i:s'), 					'account_no', $name); // record QB note
        $this->update_mysql($type . 's', $type . '_ref'		, $resp, 								'account_no', $name); // record QB's unique customer number
      }
      else
      {
        // Log failure
        // Update MySQL record (table, set field, set value, where field, where value, connection)
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

  function update_mysql($tbl, $set_field, $set_value, $where_field, $where_value) {
    $mysqli = $this->mysqli;

    $sql = "UPDATE $tbl SET $set_field = '$set_value' WHERE $where_field = '$where_value'";
    //$sql = "UPDATE trns SET txn_id='$txn_id' WHERE acctg_id = '$acctg_id'";

    if ($mysqli->query($sql) === TRUE){
    }else {
      echo $mysqli->error;
    }
  }
  
	public function get_coa() {
		$entity			= 'Account';
    $field_arr 		= array(
      'FullyQualifiedName', 
      'Id',
      'CurrentBalance',
      'CurrencyRef',
      'Classification',
      'AccountType',
      'AccountSubType',
      'Active',
      'SyncToken'
    );
		$first_element 	= $field_arr[0];
		$svc 			= 'QuickBooks_IPP_Service_'.$entity;
		//$db 			= fopen($path . "/to_receive/ChartOfAccounts.csv", 'w');
		
		$service 		= new $svc();
		$result_arr 	= array(0 => $field_arr);
		$batch_size		= 1000;//QBO's max batch size appears to be 1000 records (leave at 1000)
		$max_record		= 0;
		$toggle			= TRUE;

		//Get the data for selected fields
		do {
      //This should return an array of accounts
      $things = $service->query($this->context, $this->realm, "SELECT * FROM $entity WHERE $first_element > '$max_record' MAXRESULTS $batch_size");

      $i = 1;
      foreach ($things as $row_number=>$row){
				foreach ($field_arr as $k=>$v){
					$bits_arr = explode(":", $v);
					$z = array();
          foreach($bits_arr as $k1=>$field_name){
						$package 	= "get".$field_name;
						$z[$k1]		= $row->$package();
            
            //get rid of squiggly brackets and dash around value
					  if( in_array($field_name, array('Id', 'ParentRef', 'CurrencyRef')) ) {
              $z[$k1] = substr($z[$k1], 2, -1);
            }
					}
					$y = implode(":", $z);
          //$result_arr[$row_number][$v] = $y;
            
					$result_arr[$i][$v] = $y;
        }
        $i++;
				$max_record = $field_arr[0];
			}
		} while($batch_size == count($things));
    
    return $result_arr;
	}

	public function get_value($arr, $search_field, $search_value, $result_field)
	{
		$result = array();
		$i 		= 0;

		foreach($arr as $row){
			if($row[$search_field] == $search_value){
				$result[$i] = $row[$result_field];
				$i++;
			}
		}
		if( isset($result[0]) ){
			return $result[0];
		}else{
			return;
		}
	}
  
  //This queries a client's QBO account for specific information, e.g.
  //  get('Term', array('Id', 'Some other field')
	public function get($entity, $field_arr){
		$first_element 	= $field_arr[0];
		$svc 			= 'QuickBooks_IPP_Service_'.$entity;
		$service = new $svc();
		$result_arr = array();
		$batch_size	= 1000;//QBO's max batch size appears to be 1000 records (leave at 1000)
		$max_record	= 0;
		$toggle		= TRUE;
		$i=0;

		//echo "\nentity\n";
		//var_dump($entity);
		//echo "\nfield_arr\n";
		//var_dump($field_arr);

		//Get the data for selected fields
		do {
			//$things = $service->query($this->context, $this->realm, "SELECT * FROM $entity WHERE $first_element > '$max_record' MAXRESULTS $batch_size");
      $sql = "SELECT * FROM $entity STARTPOSITION $max_record MAXRESULTS $batch_size";
      $sql = "SELECT * FROM $entity";
      $things = $service->query($this->context, $this->realm, $sql);

			foreach ($things as $thing){
				if($toggle){
					//insert the header
					$result_arr['header']['Key'] = 'Key';
					foreach ($field_arr as $k=>$v){	
						$result_arr['header'][$v] = $v;
					}
					$toggle = FALSE;
				}
				
				//insert records
				$result_arr[$i]['Key'] = $i;
				foreach ($field_arr as $k=>$v){	
					$package 	= "get".$v;
					$z			= $thing->$package();
					if( in_array($v, array('Id', 'ParentRef')) ) {
						$z = substr($z, 2, -1);//get rid of squiggly brackets and dash around value
					}
					$result_arr[$i][$field_arr[$k]] = $z;
				}
				$i++;
				$max_record = count($result_arr) + 1;
			}
		} while($batch_size == count($things));
		return $result_arr;
	}

  public function show($entity, $field_arr, $path = '')
	{
		$first_element 	= $field_arr[0];
		$svc 			= 'QuickBooks_IPP_Service_'.$entity;
		$db 			= fopen($path . "to_receive/".$entity."_".$first_element.".csv", 'w');
		
		$service = new $svc();
		$result_arr = array();
		$batch_size	= 1000;//QBO's max batch size appears to be 1000 records (leave at 1000)
		$max_record	= 1;
		$toggle		= TRUE;
		$i=0;

		//Get the data for selected fields
		do {
			//$things = $service->query($this->context, $this->realm, "SELECT * FROM $entity WHERE $first_element > '$max_record' MAXRESULTS $batch_size");
			$things = $service->query($this->context, $this->realm, "SELECT * FROM $entity STARTPOSITION $max_record MAXRESULTS $batch_size");

			foreach ($things as $thing)
			{
				if($toggle)
				{
					//insert the header
					$result_arr['header']['Key'] = 'Key';
					foreach ($field_arr as $k=>$v)
					{	
						$result_arr['header'][$v] = $v;
					}
					$toggle = FALSE;
				}
				
				//insert records
				$result_arr[$i]['Key'] = $i;
				foreach ($field_arr as $k=>$v)
				{	
					$package 	= "get".$v;
					$z			= $thing->$package();
					if( in_array($v, array('Id', 'ParentRef')) ) {
						$z = substr($z, 2, -1);//get rid of squiggly brackets and dash around value
					}
					$result_arr[$i][$field_arr[$k]] = $z;
				}
				$result_arr[$i]['max_record'] = $max_record;
				$i++;
				$max_record = count($result_arr) + 1;
			}
		} while($batch_size == count($things));
		
		foreach($result_arr as $record)
		{
			fputcsv($db, $record);
		}
		return $result_arr;
  }

  function create_item($item, $account, $acct_arr){
		//get the id for the account
		$acct_bit = implode(":", array_slice(explode(":", $account), 1));//remove account type to leave just the fully qualified name
		$return = $this->get_value($acct_arr, 'FullyQualifiedName', $acct_bit, 'Id');

		if( $return !== NULL ){
			$id = $return;
		}else{
			//income account does not exist.  Create the income account and then create the item.
			$income_acct 	= new home_account();
			$id 			    = $income_acct->create($account, $acct_arr);
		}

		$ItemService = new QuickBooks_IPP_Service_Item();
		$Item = new QuickBooks_IPP_Object_Item();

		$Item->setName($item);
		$Item->setIncomeAccountRef($id);

		if (!$resp = $ItemService->add($this->context, $this->realm, $Item)){
			$resp = $ItemService->lastError($this->context);
		}
		return $resp;
  }


	public function create_term($data) {

		$arr 	= explode(" ", $data);
		$arr1	= explode("/", $arr[0]);

		if( count($arr) == 2 && strtolower($arr[0]) == "net" && is_numeric($arr[1]) )
		{
			//Create sales term in the form 'Net ZZ'
			$TermService = new QuickBooks_IPP_Service_Term();
			$Term = new QuickBooks_IPP_Object_Term();

			$Term->setName($data);
			$Term->setDueDays($arr[1]);
			$Term->setActive(TRUE);
			$Term->setType('STANDARD');

			if (!$resp = $TermService->add($this->context, $this->realm, $Term))
			{
				$resp = $TermService->lastError($this->context);
			}
		}
		elseif ( count($arr) == 3 && strtolower($arr[1]) == "net" && is_numeric($arr[2]) && count($arr1) == 2 && is_numeric($arr1[0]) && is_numeric($arr1[1]) ) 
		{
			//Create sales term in the form 'X/Y Net ZZ'
			$TermService = new QuickBooks_IPP_Service_Term();
			$Term = new QuickBooks_IPP_Object_Term();

			$Term->setName($data);
			$Term->setDueDays($arr[2]);
			$Term->setActive(TRUE);
			$Term->setType('STANDARD');
			$Term->setDiscountPercent($arr1[0]/100);
			$Term->setDiscountDays($arr1[1]);

			if (!$resp = $TermService->add($this->context, $this->realm, $Term))
			{
				$resp = $TermService->lastError($this->context);
			}
		}
		else
		{
			$resp = 1;//set term to 'Due on receipt'
		}
		return $resp;
  }
  
	public function create_account($data, $acct_arr) {
	
		$using_account_types = TRUE;
		$return		= NULL;
		$new_arr	= array();
		$arr 		= explode(":", $data);
	  $account_type 		= array('Bank'=>'CashOnHand', 'Other Current Asset'=>'EmployeeCashAdvances', 'Fixed Asset'=>'FurnitureAndFixtures', 'Other Asset'=>'Licenses',
					'Accounts Receivable'=>'AccountsReceivable', 'Equity'=>'OpeningBalanceEquity', 'Expense'=>'Travel', 'OtherExpense'=>'Depreciation', 
					'CostOfGoodsSold'=>'CostOfLaborCos', 'Accounts Payable'=>'AccountsPayable', 'CreditCard'=>'CreditCard', 'LongTermLiability'=>'NotesPayable',
					'OtherCurrentLiability'=>'OtherCurrentLiabilities', 'Income'=>'OtherPrimaryIncome', 'OtherIncome'=>'OtherInvestmentIncome');

		$account_sub_type 	= array('Bank'=>'CashOnHand', 'Bank'=>'Checking', 'Bank'=>'MoneyMarket', 'Bank'=>'RentsHeldInTrust', 'Bank'=>'Savings', 'Bank'=>'TrustAccounts', 
					'Other Current Asset'=>'AllowanceForBadDebts', 'Other Current Asset'=>'DevelopmentCosts', 'Other Current Asset'=>'EmployeeCashAdvances', 
					'Other Current Asset'=>'OtherCurrentAssets', 'Other Current Asset'=>'Inventory', 'Other Current Asset'=>'Investment_MortgageRealEstateLoans', 
					'Other Current Asset'=>'Investment_Other', 'Other Current Asset'=>'Investment_TaxExemptSecurities', 'Other Current Asset'=>'Investment_USGovernmentObligations', 
					'Other Current Asset'=>'LoansToOfficers', 'Other Current Asset'=>'LoansToOthers', 'Other Current Asset'=>'LoansToStockholders', 
					'Other Current Asset'=>'PrepaidExpenses', 'Other Current Asset'=>'Retainage', 'Other Current Asset'=>'UndepositedFunds', 
					'Fixed Asset'=>'AccumulatedDepletion', 'Fixed Asset'=>'AccumulatedDepreciation', 'Fixed Asset'=>'DepletableAssets', 
					'Fixed Asset'=>'FurnitureAndFixtures', 'Fixed Asset'=>'Land', 'Fixed Asset'=>'LeaseholdImprovements', 'Fixed Asset'=>'OtherFixedAssets', 
					'Fixed Asset'=>'AccumulatedAmortization', 'Fixed Asset'=>'Buildings', 'Fixed Asset'=>'IntangibleAssets', 'Fixed Asset'=>'MachineryAndEquipment', 
					'Fixed Asset'=>'Vehicles', 'Other Asset'=>'LeaseBuyout', 'Other Asset'=>'OtherLongTermAssets', 'Other Asset'=>'SecurityDeposits', 
					'Other Asset'=>'AccumulatedAmortizationOfOtherAssets', 'Other Asset'=>'Goodwill', 'Other Asset'=>'Licenses', 'Other Asset'=>'OrganizationalCosts', 
					'Accounts Receivable'=>'AccountsReceivable', 'Equity'=>'OpeningBalanceEquity', 'Equity'=>'PartnersEquity', 'Equity'=>'RetainedEarnings', 
					'Equity'=>'AccumulatedAdjustment', 'Equity'=>'OwnersEquity', 'Equity'=>'PaidInCapitalOrSurplus', 'Equity'=>'​PartnerContributions', 'Equity'=>'PartnerDistributions', 
					'Equity'=>'PreferredStock', 'Equity'=>'CommonStock', 'Equity'=>'TreasuryStock', 'Expense'=>'AdvertisingPromotional', 'Expense'=>'BadDebts', 
					'Expense'=>'BankCharges', 'Expense'=>'CharitableContributions', 'Expense'=>'Entertainment', 'Expense'=>'EntertainmentMeals', 'Expense'=>'EquipmentRental', 
					'Expense'=>'GlobalTaxExpense', 'Expense'=>'Insurance', 'Expense'=>'InterestPaid', 'Expense'=>'LegalProfessionalFees', 'Expense'=>'OfficeGeneralAdministrativeExpenses', 
					'Expense'=>'OtherMiscellaneousServiceCost', 'Expense'=>'PromotionalMeals', 'Expense'=>'RentOrLeaseOfBuildings', 'Expense'=>'RepairMaintenance', 
					'Expense'=>'ShippingFreightDelivery', 'Expense'=>'SuppliesMaterials', 'Expense'=>'Travel', 'Expense'=>'TravelMeals', 'Expense'=>'Utilities', 'Expense'=>'Auto', 
					'Expense'=>'CostOfLabor', 'Expense'=>'DuesSubscriptions', 'Expense'=>'PayrollExpenses', 'Expense'=>'TaxesPaid', 'OtherExpense'=>'Depreciation', 
					'OtherExpense'=>'ExchangeGainOrLoss', 'OtherExpense'=>'OtherMiscellaneousExpense', 'OtherExpense'=>'PenaltiesSettlements', 'OtherExpense'=>'Amortization', 
					'CostOfGoodsSold'=>'EquipmentRentalCos', 'CostOfGoodsSold'=>'OtherCostsOfServiceCos', 'CostOfGoodsSold'=>'ShippingFreightDeliveryCos', 
					'CostOfGoodsSold'=>'SuppliesMaterialsCogs', 'CostOfGoodsSold'=>'CostOfLaborCos', 'Accounts Payable'=>'AccountsPayable', 'CreditCard'=>'CreditCard', 
					'LongTermLiability'=>'NotesPayable', 'LongTermLiability'=>'OtherLongTermLiabilities', 'LongTermLiability'=>'ShareholderNotesPayable', 
					'OtherCurrentLiability'=>'DirectDepositPayable', 'OtherCurrentLiability'=>'LineOfCredit', 'OtherCurrentLiability'=>'LoanPayable', 
					'OtherCurrentLiability'=>'GlobalTaxPayable', 'OtherCurrentLiability'=>'GlobalTaxSuspense', 'OtherCurrentLiability'=>'OtherCurrentLiabilities', 
					'OtherCurrentLiability'=>'PayrollClearing', 'OtherCurrentLiability'=>'PayrollTaxPayable', 'OtherCurrentLiability'=>'PrepaidExpensesPayable', 
					'OtherCurrentLiability'=>'RentsInTrustLiability', 'OtherCurrentLiability'=>'TrustAccountsLiabilities', 'OtherCurrentLiability'=>'FederalIncomeTaxPayable', 
					'OtherCurrentLiability'=>'InsurancePayable', 'OtherCurrentLiability'=>'SalesTaxPayable', 'OtherCurrentLiability'=>'StateLocalIncomeTaxPayable', 
					'Income'=>'NonProfitIncome', 'Income'=>'OtherPrimaryIncome', 'Income'=>'SalesOfProductIncome', 'Income'=>'ServiceFeeIncome', 'Income'=>'DiscountsRefundsGiven', 
					'OtherIncome'=>'DividendIncome', 'OtherIncome'=>'InterestEarned', 'OtherIncome'=>'OtherInvestmentIncome', 'OtherIncome'=>'OtherMiscellaneousIncome', 
					'OtherIncome'=>'TaxExemptInterest');

		if($using_account_types)
		{
			$parent_accountsubtype = $account_type[$arr[0]];
			$arr = array_slice($arr, 1);//remove the account type
			if(count($arr)>5) { return 'Only 5 account levels allowed'; }
		}
		else
		{
			return;
		}

		do {
			$arr = implode(":", $arr);
			if( isset($acct_arr[$arr]['Id']) )
			{
				$new_arr[$arr]['Id'] 				        = $acct_arr[$arr]['Id'];
				$new_arr[$arr]['AccountSubType'] 	  = $acct_arr[$arr]['AccountSubType'];
				$parent_id 							            = $acct_arr[$arr]['Id'];
				$parent_accountsubtype				      = $acct_arr[$arr]['AccountSubType'];
			}
			else
			{
				$new_arr[$arr]['Id'] = NULL;
			}
			$arr = explode(":", $arr);
			$arr = array_slice($arr, 0, -1);
		} while( count($arr) > 0 );

		$new_arr = array_reverse($new_arr);

		foreach($new_arr as $k=>$v)
		{
			if($v['Id'] == NULL)
			{
				$AccountService = new QuickBooks_IPP_Service_Account();
				$Account = new QuickBooks_IPP_Object_Account();
				$acct_name = array_slice(explode(":", $k), -1);
				$Account->setName($acct_name);
				if( isset($parent_id) && $parent_id !== NULL)
				{
					$Account->setSubAccount(TRUE);
					$Account->setAccountSubType($parent_accountsubtype);
					$Account->setParentRef($parent_id);
				}
				else
				{
					$Account->setSubAccount(FALSE);
					$Account->setAccountSubType($parent_accountsubtype);
				}
				if (!$resp = $AccountService->add($this->context, $this->realm, $Account))
				{
					$resp = $AccountService->lastError($this->context);
					return $resp;
				}
				else
				{
					$parent_id = $resp;
				}
			}
			else
			{
				$parent_id 				= $v['Id'];
				$parent_accountsubtype	= $v['AccountSubType'];
				$resp 					= $v['Id'];
			}
		}
		return $resp;
 	}

 	public function get_account($data, $acct_arr='') {
 		//In QBO, account names are unique

	 	$return = NULL;

	 	//try fully qualified name, assuming account type is prepended
	 	$arr = explode (":", $data);
	 	
	 	$arr = array_slice($arr, 1); 
	 	$arr = implode(":", $arr);
	 	if(isset($acct_arr[$arr]['Id']))
	 	{
	 		$return = $acct_arr[$arr]['Id'];
	 	}
		return $return; 
  }
  
	public function create_class($data, $class_arr) {

		if(count(explode(":", $data))>5) { return 'Only 5 class levels allowed'; }

		$return		= NULL;
		$new_arr	= array();
		$arr 		= explode(":", $data);

		do {
			$arr = implode(":", $arr);
			if( isset($class_arr[$arr]['Id']) )
			{
				$new_arr[$arr] = $class_arr[$arr]['Id'];
				$parent_id = $class_arr[$arr]['Id'];
			}
			else
			{
				$new_arr[$arr] = NULL;
			}
			$arr = explode(":", $arr);
			$arr = array_slice($arr, 0, -1);
		} while( count($arr) > 0 );

		$new_arr = array_reverse($new_arr);

		foreach($new_arr as $k=>$v)
		{
			if($v == NULL)
			{
				$ClassService = new QuickBooks_IPP_Service_Class();
				$Class = new QuickBooks_IPP_Object_Class();
				$class_name = array_slice(explode(":", $k), -1);
				$Class->setName($class_name);
				if($parent_id == NULL)
				{
					$Class->setSubClass(FALSE);
				}
				else
				{
					$Class->setSubClass(TRUE);
					$Class->setParentRef($parent_id);
				}
				if (!$resp = $ClassService->add($this->context, $this->realm, $Class))
				{
					$resp = $ClassService->lastError($this->context);
					return $resp;
				}
				else
				{
					$parent_id = $resp;
				}
			}
			else
			{
				$parent_id = $v;
				$resp = $v;
			}
		}
		return $resp;
  }

}


?>
