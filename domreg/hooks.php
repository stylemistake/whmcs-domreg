<?php

require_once "domreg.php";
require_once "domreg.class.php";

function domreg_getDomain( $id ) {
    $id = intval( $id );
    $query = select_query( "tbldomains", "*", array( "id" => $id ));
    $result = mysql_fetch_array( $query );
    if ( ! $result ) {
        return false;
    }
    return $result;
}

function domreg_createFieldList( $fields ) {
    $result = array();
    foreach ( $fields as $i => $f ) {
        $result[ $f["label"] ] = '<input type="text" '
            . 'name="' . $i . '" '
            . 'value="' . ( $f["value"] ? $f["value"] : "" ) . '" '
            . ( $f["disabled"] ? "disabled" : "" ) . '>'
            . ( $f["hint"] ? '&nbsp;' . $f["hint"] : "" );
    }
    return $result;
}

function hook_domreg_CustomFields( $data ) {
    // Get missing data
    $data = domreg_getDomain( $data["id"] );
    // Don't do anything if registrar is not Domreg
    if ( $data["registrar"] != "domreg" ) {
        return false;
    }
    // Set up common vars
    $domain = $data["domain"];
    $client_id = intval( $data["userid"] );
    $fields = array(
        "domain_rn" => array(
            "label" => "Domain RN",
            "hint" => "RN associated with this domain",
            "disabled" => true,
        ),
        "client_rn" => array(
            "label" => "Client RN",
            "hint" => "RN associated with this WHMCS account",
        ),
    );
    // Set up additional fields
    try {
        $domreg = Domreg::getInstance();
        try {
            $registrant = $domreg->api->getRegistrantByDomain( $domain );
            $fields["domain_rn"]["value"] = $registrant["id"];
        } catch ( DomregAPIExecutorException $e ) {
            $fields["domain_rn"]["hint"] = "Response from Domreg: " . $e->getMessage();
        }
        try {
            $fields["client_rn"]["value"] = $domreg->registrants->getRegistrantId( $client_id );
        } catch ( DomregAPIExecutorException $e ) {
            $fields["client_rn"]["hint"] = "Error quering database: " . $e->getMessage();
        }
    } catch ( Exception $e ) {
        $fields["domain_rn"]["hint"] = "Fatal: " . $e->getMessage();
    }
    return domreg_createFieldList( $fields );
}

function hook_domreg_CustomFieldsSave( $data ) {
    // Don't do anything if registrar is not Domreg
    if ( $data["registrar"] != "domreg" ) {
        return $data;
    }
    // Save additional fields
    try {
        $domreg = Domreg::getInstance();
        $current_rn = $domreg->registrants->getRegistrantId( $data["userid"] );
        if ( $data["client_rn"] ) {
            if ( ! $current_rn || $current_rn != $data["client_rn"] ) {
                $domreg->registrants->setRegistrantId( $data["userid"], $data["client_rn"] );
            }
        } else {
            $domreg->registrants->setRegistrantId( $data["userid"], null );
        }
    } catch ( DomregAPIExecutorException $e ) {
        var_export( $e );
    } catch ( DomregRegistrantsTableException $e ) {
        var_export( $e );
    }
    return $data;
}

function hook_domreg_CheckTransferStatus( $data ) {
    $domain = $data["sld"] . "." . $data["tld"];
}

add_hook( "AdminClientDomainsTabFields", 1, "hook_domreg_CustomFields" );
add_hook( "AdminClientDomainsTabFieldsSave", 1, "hook_domreg_CustomFieldsSave" );
