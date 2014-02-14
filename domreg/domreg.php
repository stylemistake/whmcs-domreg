<?php

define( "WHMCS_MODULE", "DOMREG" );
require_once("domreg.class.php");

function domreg_getConfigArray() {
	return array(
		"Username" => array(
			"Type" => "text", "Size" => "20", "Default" => "",
			"Description" => "Domreg.lt RN login"
		),
		"Password" => array(
			"Type" => "password", "Size" => "20", "Default" => "",
			"Description" => "Domreg.lt RN password"
		),
		"TestMode" => array(
			"Type" => "yesno", "Default" => "no",
			"Description" => "Testing mode"
		),
		"RegistrantsTable" => array(
			"Type" => "text", "Size" => "20", "Default" => "domreg_registrants",
			"Description" => "Default registrants table (automatically created)"
		),
		"SupportContact" => array(
			"Type" => "text", "Size" => "20", "Default" => "",
			"Description" => "Domreg.lt support contact (eg. CN1234)"
		),
	);
}

function domreg_GetNameservers( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$result = $domreg->get_ns_servers( $domain );
	$domreg->logout();
	if ( ! $result ) $error = $domreg->error;
	else {
		foreach ( $result as $i => $value ) $values[ "ns". ($i+1) ] = $value;
	}
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_SaveNameservers($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	for ( $i = 1; $i <= 4; $i++ ) $ns[] = $params["ns".$i];
	$domreg = new Domreg( $params );
	$result = $domreg->set_ns_servers( $domain, $ns );
	$domreg->logout();
	if ( ! $result ) $error = $domreg->error;
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,domreg_GetNameservers($params),$processeddata,$replacevars);
	return $values;
}

/*
function domreg_GetRegistrarLock($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$lockstatus="locked";
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $lockstatus;
}

function domreg_SaveRegistrarLock($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$lockstatus="locked";
	$values["error"] = "Domreg's current policy doesn't allow changing registrar locks.";
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_GetEmailForwarding($params) {
	__domreg_log("Get email fwd...");
	$domain = $params["sld"] . "." . $params["tld"];
	foreach ($result AS $value) {
		$values[$counter]["prefix"] = $value["prefix"];
		$values[$counter]["forwardto"] = $value["forwardto"];
	}
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_SaveEmailForwarding($params) {
	__domreg_log("Save email fwd...");
	$domain = $params["sld"] . "." . $params["tld"];
	foreach ($params["prefix"] AS $key=>$value) {
		$forwardarray[$key]["prefix"] =  $params["prefix"][$key];
		$forwardarray[$key]["forwardto"] =  $params["forwardto"][$key];
	}
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
}
*/

function domreg_GetDNS($params) {
	__domreg_log("Get DNS...");
	$domain = $params["sld"] . "." . $params["tld"];
	$hostrecords = array();
	$hostrecords[] = array( "hostname" => "ns1", "type" => "A", "address" => "192.168.0.1", );
	$hostrecords[] = array( "hostname" => "ns2", "type" => "A", "address" => "192.168.0.2", );
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $hostrecords;
}

function domreg_SaveDNS($params) {
	__domreg_log("Set DNS...");
	$domain = $params["sld"] . "." . $params["tld"];
	# Loop through the submitted records
	foreach ( $params["dnsrecords"] as $key => $values ) {
		$hostname = $values["hostname"];
		$type = $values["type"];
		$address = $values["address"];
		# Add your code to update the record here
	}
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_RegisterDomain($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	for ( $i = 1; $i <= 4; $i++ ) $ns[] = $params["ns".$i];
	$domreg = new Domreg( $params );
	$registrant = $domreg->sync_registrant();
	if ( ! $registrant ) $error = $domreg->error;
	else {
		$response = $domreg->create_domain( $domain, $registrant, $ns );
		if ( ! $response ) $error = $domreg->error;
	}
	$processeddata = $domreg->executor->errorMsg;
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_TransferDomain($params) {
	__domreg_log("Transferring domain...");
	$domain = $params["sld"] . "." . $params["tld"];
	$transfersecret = $params["transfersecret"];
	# If error, return the error message in the value below
	// $values["error"] = $error;
	$values["error"] = "Domain transfering function is temporarily disabled.";
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_RenewDomain($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$regperiod = $params["regperiod"];
	$domreg = new Domreg( $params );
	$response = $domreg->renew_domain( $domain );
	if ( ! $response ) $error = $domreg->error;
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_RequestDelete($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->delete_domain( $domain );
	if ( ! $response ) $error = $domreg->error;
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

/*
function domreg_GetContactDetails($params) {
	__domreg_log("Getting contact details...");
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->get_domain_info( $domain );
	if ( ! $response ) $error = $domreg->error;
	else {
		__domreg_log( $response );
		$values["Registrant"]["Full Name"] = $response["registrant"]["name"];
		$values["Registrant"]["Phone number"] = $response["registrant"]["name"];
	}
	// $registrant = array(
	// 	"client_id" => $params["id"],
	// 	"name" => trim( $params["firstname"] . " " . $params["lastname"] ),
	// 	"street" => trim( $params["address1"] . " " . $params["address2"] ),
	// 	"city" => $params["city"],
	// 	"cc" => strtolower( $params["country"] ),
	// 	"voice" => $this->convert_phone_number( $params["phonenumber"] ),
	// 	"email" => strtolower( $params["email"] ),
	// 	"role" => "registrant"
	// );
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_SaveContactDetails($params) {
	__domreg_log("Saving contact details...");
	$domain = $params["sld"] . "." . $params["tld"];
	# Data is returned as specified in the GetContactDetails() function
	// $firstname = $params["contactdetails"]["Registrant"]["First Name"];
	// $lastname = $params["contactdetails"]["Registrant"]["Last Name"];
	// $adminfirstname = $params["contactdetails"]["Admin"]["First Name"];
	// $adminlastname = $params["contactdetails"]["Admin"]["Last Name"];
	// $techfirstname = $params["contactdetails"]["Tech"]["First Name"];
	// $techlastname = $params["contactdetails"]["Tech"]["Last Name"];
	# Put your code to save new WHOIS data here
	// $domreg = new Domreg( $params );
	// $response = $domreg->update_registrant();
	// if ( ! $response ) $error = $domreg->error;
	// $domreg->logout();
	# If error, return the error message in the value below
	$values["error"] = $error;
	$values["error"] = "Domreg doesn't allow changing contact details";
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}
*/

/*
function domreg_GetEPPCode($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	# Put your code to request the EPP code here - if the API returns it, pass back as below - otherwise return no value and it will assume code is emailed
	$values["eppcode"] = $eppcode;
	__domreg_log("Get EPP code...");
	# If error, return the error message in the value below
	$values["error"] = $error;
	$values["error"] = "Domreg doesn't issue EPP codes";
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}
*/

function domreg_RegisterNameserver($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$nameserver = $params["nameserver"];
	$ipaddress = $params["ipaddress"];
	# Put your code to register the nameserver here
	__domreg_log("Creating nameserver...");
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_ModifyNameserver($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$nameserver = $params["nameserver"];
	$currentipaddress = $params["currentipaddress"];
	$newipaddress = $params["newipaddress"];
	# Put your code to update the nameserver here
	__domreg_log("Modifying nameserver...");
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}

function domreg_DeleteNameserver($params) {
	$domain = $params["sld"] . "." . $params["tld"];
	$nameserver = $params["nameserver"];
	# Put your code to delete the nameserver here
	__domreg_log("Deleting nameserver...");
	# If error, return the error message in the value below
	$values["error"] = $error;
	logModuleCall(WHMCS_MODULE,__FUNCTION__,$params,$domainInfo,$processeddata,$replacevars);
	return $values;
}
