<?php

//
/*	This file creates a base class that is extended by the transaction classes (eg invoice.php, bill.php, etc.)
*	@programmer Craig Millis
*   Which files call this file: all of the transaction files like invoice, bill, ...
*   Debugging notes:
*   How this file will be used: this file retrieves data from QBO and uses it to copy MySQL data to QBO.  It also sends the data to a validation script before copying.
*   
*	
*/

class transaction {

	protected $IPP;
	protected $context;
	protected $realm;
	protected $creds;
	protected $path;
	protected $filename;
	
	function __construct($path = "", $filename = "") {
		// Set up the IPP instance
		$this->IPP = new QuickBooks_IPP(QUICKBOOKS2_DSN);

		// Setup IntuitAnywhere
		$IntuitAnywhere = new QuickBooks_IPP_IntuitAnywhere(QUICKBOOKS2_DSN, QUICKBOOKS2_ENCRYPT_KEY, QUICKBOOKS2_CONSUMER_KEY, QUICKBOOKS2_CONSUMER_SECRET, QUICKBOOKS2_OAUTH_URL, QUICKBOOKS2_SUCCESS_URL);

		// Get our OAuth credentials from the database
		$this->creds = $IntuitAnywhere->load(QUICKBOOKS2_USERNAME, QUICKBOOKS2_TENANT);

		// Tell the framework to load some data from the OAuth store.
		$this->IPP->authMode(
			QuickBooks_IPP::AUTHMODE_OAUTH, 
			QUICKBOOKS2_USERNAME, 
			$this->creds);
		
		// Print the credentials we're using
		//print_r($creds);

		// This is our current realm
		$this->realm = $this->creds['qb_realm'];

		// Load the OAuth information from the database
		if ($this->context = $this->IPP->context())
		{
			// Set the IPP version to v3 
			$this->IPP->version(QuickBooks_IPP_IDS::VERSION_3);
		}

		// Define the path and filenames so that the child transactions can access these and log success or failure
		$this->path 	= $path;
		$this->filename = $filename;
	}
}

?>
