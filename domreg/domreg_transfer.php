<?php

// WHMCS constants and functions
require_once dirname(__FILE__) . '/../../../dbconnect.php';
require_once ROOTDIR . '/includes/functions.php';
require_once ROOTDIR . '/includes/registrarfunctions.php';

// Include paths
$include_path = ROOTDIR . '/modules/registrars/domreg';
set_include_path( $include_path . PATH_SEPARATOR . get_include_path() );

require_once "domreg.class.php";

$params = getregistrarconfigoptions("domreg");
$domreg = new Domreg( $params, array(
	"log_to_console" => true,
	"log_to_whmcs" => false,
));

$domain = "test.lt";

$registrant = $domreg->db->find_registrant( array( "id" => "RN1234" ) );
$domreg->log( "registrant", $registrant );
$domreg->log( "errors", $domreg->error );
echo "\n";

if ( $registrant ) {
	$response = $domreg->transfer_domain( $domain, $registrant );
	$domreg->log( "transfer", $response );
	$domreg->log( "errors", $domreg->error );
	echo "\n";

	$response = $domreg->trade_domain( $domain, $registrant );
	$domreg->log( "trade", $response );
	$domreg->log( "errors", $domreg->error );
	echo "\n";
}
