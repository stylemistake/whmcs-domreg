<?php

define( "WHMCS_MODULE", "DOMREG" );
require_once "domreg.class.php";

function domreg_getConfigArray() {
    return array(
        "Username" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt RN login (required)"
        ),
        "Password" => array(
            "Type" => "password", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt RN password (required)"
        ),
        "TestMode" => array(
            "Type" => "dropdown", "Options" => "Enable,Disable", "Default" => "Enable",
            "Description" => "Testing mode"
        ),
        "RegistrantsTable" => array(
            "Type" => "text", "Size" => "20", "Default" => "mod_domreg_registrants",
            "Description" => "Default registrants table (automatically created)"
        ),
        "SupportContact" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt support contact (eg. CN1234, required)"
        ),
        "NsGroup1" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt nameserver group 1 (optional)"
        ),
        "NsGroup2" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt nameserver group 2 (optional)"
        ),
        "NsGroup3" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt nameserver group 3 (optional)"
        ),
        "NsGroup4" => array(
            "Type" => "text", "Size" => "20", "Default" => "",
            "Description" => "Domreg.lt nameserver group 4 (optional)"
        ),
        "AdminUser" => array(
            "Type" => "text", "Size" => "20", "Default" => "admin",
            "Description" => "WHMCS Admin User (for API)"
        )
    );
}

function domreg_AdminCustomButtonArray() {
    return array(
        "Sync" => "SyncManual",
    );
}

function domreg_ClientAreaCustomButtonArray() {
    return array(
        "requestrn" => "RequestRn",
    );
}

function domreg_RequestRn( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->getRegistrantByDomain( $domain );
        $domreg->api->logout();
        return array(
            "templatefile" => "requestrn",
            "vars" => array(
                "registrant_id" => $response["id"],
                "error" => $error,
            ),
        );
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_GetNameservers( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->getNs( $domain );
        $domreg->api->logout();
        foreach ( $response["ns"] as $i => $ns ) {
            $values[ "ns". ($i+1) ] = $ns;
        }
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_SaveNameservers( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    for ( $i = 1; $i <= 4; $i++ ) {
        $ns[] = $params[ "ns".$i ];
    }
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->setNs( $domain, $ns );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_RegisterDomain( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $client_id = $params["userid"];
    $regperiod = $params["regperiod"];
    for ( $i = 1; $i <= 4; $i++ ) {
        $ns[] = $params[ "ns".$i ];
    }
    try {
        $domreg = new Domreg( $params );
        $registrant = $domreg->requireRegistrant( $client_id, $domain );
        $response = $domreg->api->createDomain( $domain, $registrant, $ns, $regperiod );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_TransferDomain( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $transfersecret = $params["transfersecret"];
    $client_id = $params["userid"];
    for ( $i = 1; $i <= 4; $i++ ) {
        $ns[] = $params[ "ns".$i ];
    }
    try {
        $domreg = new Domreg( $params );
        $registrant = $domreg->requireRegistrant( $client_id, $domain );
        $response = $domreg->api->transferDomain( $domain, $registrant, $ns );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_RenewDomain( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $regperiod = $params["regperiod"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->renewDomain( $domain, $regperiod );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_RequestDelete( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->deleteDomain( $domain );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_GetContactDetails( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $registrant = $domreg->api->getRegistrantByDomain( $domain );
        $values["Registrant"]["Email"] = $registrant["email"];
        $values["Registrant"]["Phone Number"] = $registrant["voice"];
        $values["Registrant"]["Street"] = $registrant["street"];
        $values["Registrant"]["City"] = $registrant["city"];
        $values["Registrant"]["Region"] = $registrant["sp"];
        $values["Registrant"]["Post code"] = $registrant["pc"];
        $values["Registrant"]["Country code"] = $registrant["cc"];
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $registrant, $error );
    return $values;
}

function domreg_SaveContactDetails( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $regdata = $params["contactdetails"]["Registrant"];
    try {
        $domreg = new Domreg( $params );
        $registrant = $domreg->api->getRegistrantByDomain( $domain );
        $registrant = DomregRegistrant::createFromWHMCSParams( $regdata, $registrant );
        $response = $domreg->api->updateRegistrant( $registrant );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_RegisterNameserver( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->addNs( $domain, $params["nameserver"], $params["ipaddress"] );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_ModifyNameserver( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->deleteNs( $domain, $params["nameserver"] );
        $response = $domreg->api->addNs( $domain, $params["nameserver"], $params["newipaddress"] );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_DeleteNameserver( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $response = $domreg->api->deleteNs( $domain, $params["nameserver"] );
        $domreg->api->logout();
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $response, $error );
    return $values;
}

function domreg_TransferSync( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    try {
        $domreg = new Domreg( $params );
        $domain_info = $domreg->api->getDomainInfo( $domain );
        $domreg->api->logout();
        $values["active"] = ( $domain_info["status"] == "registered" );
        $values["registrationdate"] = $domain_info["date_created"];
        $values["expirydate"] = $domain_info["date_expires"];
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $domain_info, $error );
    return $values;
}


function domreg_Sync( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $domreg = new Domreg( $params );
    try {
        $domreg = new Domreg( $params );
        // Try polling first
        $messages = $domreg->api->poll();
        if (!empty($messages)) {
            $domreg->log('poll', $messages);
        }
        // Try updating a domain
        $domain_info = $domreg->api->getDomainInfo( $domain );
        $domreg->api->logout();
        $values["active"] = ( $domain_info["status"] == "registered" );
        $values["registrationdate"] = $domain_info["date_created"];
        $values["expirydate"] = $domain_info["date_expires"];
    } catch ( DomregAPIExecutorException $e ) {
        if ( $domreg->executor->status["rcode"] == "2201" ) {
            localAPI( "updateclientdomain", array(
                "domainid" => $params["domainid"],
                "status" => "Cancelled"
            ), $params["AdminUser"] );
        } else {
            $error = $e->getMessage();
        }
    } catch ( Exception $e ) {
        $error = $e->getMessage();
    }
    $values["error"] = $error;
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $domain_info, $error );
    return $values;
}

function domreg_SyncManual( $params ) {
    $domain = $params["sld"] . "." . $params["tld"];
    $values = domreg_Sync( $params );
    $time_grace = intval( $GLOBALS["CONFIG"]["OrderDaysGrace"] ) * 86400;
    $sync_data = array(
        "domainid" => $params["domainid"],
        "regdate" => date( "Ymd", strtotime( $values["registrationdate"] ) ),
        "expirydate" => date( "Ymd", strtotime( $values["expirydate"] ) ),
        "nextduedate" => date( "Ymd", strtotime( $values["expirydate"] ) - $time_grace ),
    );
    if ( $values["active"] ) {
        $sync_data["status"] = "active";
    }
    $result = localAPI( "updateclientdomain", $sync_data, $params["AdminUser"] );
    logModuleCall( WHMCS_MODULE, __FUNCTION__, $params, $result, $values["error"] );
    return array(
        "message" => "(Warning) You must refresh page to see the changes"
    );
}
