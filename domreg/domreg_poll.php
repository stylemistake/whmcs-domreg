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
	"log_to_console" => true
));

$domreg->poll();
