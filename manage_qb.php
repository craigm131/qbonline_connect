<?php

///This file is use to delete or update records in QBO.  DO NOT USE this unless you know what you're doing.

require_once('transaction.php');

class manage_qb extends transaction {

	public function get_vend($cust_client, $path) {

		$VendorService 	= new QuickBooks_IPP_Service_Vendor();

		$vendors 	= $VendorService->query($this->context, $this->realm, "SELECT * FROM Vendor");
		
		$fp = fopen(substr($path, 0, -7) . "to_receive/vendors.csv", 'w');

		if (!empty($vendors))
		{
			foreach($vendors as $Vendor)
			{
				$id = $Vendor->getId();
				$name = $Vendor->getDisplayName();
				echo "\n$name $id";
				fputcsv($fp, array($name, $id));
			}
		}
	}

	public function get_cust($cust_client, $path) {

		$CustomerService 	= new QuickBooks_IPP_Service_Customer();
		$fp = fopen(substr($path, 0, -7) . "to_receive/customers.csv", 'w');

		$customers 	= $CustomerService->query($this->context, $this->realm, "SELECT * FROM Customer");

		if (!empty($customers))
		{
			foreach($customers as $Customer)
			{
				$id = $Customer->getId();
				$name = $Customer->getDisplayName();
				echo "\n$name $id";
				fputcsv($fp, array($name, $id));
			}
		}
	}
}

?>
