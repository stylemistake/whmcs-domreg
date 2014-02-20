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

function domreg_ClientAreaCustomButtonArray() {
	return array(
		"Request client's ID" => "RequestClientId",
	);
}

function domreg_RequestClientId( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->get_domain_info( $domain );
	if ( ! $response ) $error = $domreg->error;
	else $registrant = $response["registrant"];
	return array(
		"templatefile" => "client_id",
		"breadcrumb" => array(
			"clientarea.php?action=domaindetails&domainid=" . $domain_id . "&modop=custom&a=RequestClientId" => "RequestClientId"
		),
		"vars" => array(
			"client_id" => $registrant["id"],
			"error" => $error,
		),
	);
}

function domreg_GetNameservers( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->get_ns_servers( $domain );
	if ( ! $response ) $error = $domreg->error;
	else {
		foreach ( $response as $i => $value ) $values[ "ns". ($i+1) ] = $value;
	}
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_SaveNameservers( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	for ( $i = 1; $i <= 4; $i++ ) $ns[] = $params[ "ns".$i ];
	$domreg = new Domreg( $params );
	$response = $domreg->set_ns_servers( $domain, $ns );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_RegisterDomain( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	for ( $i = 1; $i <= 4; $i++ ) $ns[] = $params[ "ns".$i ];
	$domreg = new Domreg( $params );
	$registrant = $domreg->sync_registrant();
	if ( ! $registrant ) $error = $domreg->error;
	else {
		$response = $domreg->create_domain( $domain, $registrant, $ns );
		if ( ! $response ) $error = $domreg->error;
	}
	$processeddata = $domreg->executor->errorMsg;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $processeddata );
	return $values;
}

function domreg_TransferDomain( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$transfersecret = $params["transfersecret"];
	for ( $i = 1; $i <= 4; $i++ ) $ns[] = $params[ "ns".$i ];
	$domreg = new Domreg( $params );
	$registrant = $domreg->sync_registrant();
	if ( ! $registrant ) $error = $domreg->error;
	else {
		$domreg->transfer_domain( $domain, $registrant, $ns );
		if ( ! $response ) $error = $domreg->error;
	}
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_RenewDomain( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$regperiod = $params["regperiod"];
	$domreg = new Domreg( $params );
	$response = $domreg->renew_domain( $domain );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_RequestDelete( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->delete_domain( $domain );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_GetContactDetails( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->get_domain_info( $domain );
	if ( ! $response ) $error = $domreg->error;
	else {
		$values["Registrant"]["Email"] = $response["registrant"]["email"];
		$values["Registrant"]["Phone Number"] = $response["registrant"]["voice"];
		$values["Registrant"]["Street"] = $response["registrant"]["street"];
		$values["Registrant"]["City"] = $response["registrant"]["city"];
		$values["Registrant"]["Region"] = $response["registrant"]["sp"];
		$values["Registrant"]["Post code"] = $response["registrant"]["pc"];
		$values["Registrant"]["Country code"] = $response["registrant"]["cc"];
	}
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_SaveContactDetails( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$regdata = $params["contactdetails"]["Registrant"];
	$domreg = new Domreg( $params );
	$response = $domreg->get_domain_info( $domain );
	if ( ! $response ) $error = $domreg->error;
	else {
		$registrant = $domreg->params_to_registrant( $regdata, $response["registrant"] );
		$response = $domreg->update_registrant( $registrant );
		if ( ! $response ) $error = $domreg->error;
	}
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_RegisterNameserver( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->register_ns( $domain, $params["nameserver"], $params["ipaddress"] );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_ModifyNameserver( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$domreg->delete_ns( $domain, $params["nameserver"] );
	$response = $domreg->register_ns( $domain, $params["nameserver"], $params["newipaddress"] );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}

function domreg_DeleteNameserver( $params ) {
	$domain = $params["sld"] . "." . $params["tld"];
	$domreg = new Domreg( $params );
	$response = $domreg->delete_ns( $domain, $params["nameserver"] );
	if ( ! $response ) $error = $domreg->error;
	$values["error"] = $error;
	logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
	return $values;
}
