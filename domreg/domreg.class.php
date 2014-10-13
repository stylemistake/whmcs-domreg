<?php

// --------------------------------------------------------
//  DomregRegistrantsTable
//  Local registrants database
// --------------------------------------------------------

class DomregRegistrantsTableException extends Exception {}

class DomregRegistrantsTable {

	public $table;
	public $fields = "client_id,registrant_id";

	public function __construct( $options = array() ) {
		if ( ! $options["table"] ) {
			$this->table = $options["isTestMode"]
				? "mod_domreg_registrants_test"
				: "mod_domreg_registrants";
		} else {
			$this->table = $options["isTestMode"]
				? $options["table"] . "_test"
				: $options["table"];
		}
		// Initialize database
		return full_query("
		CREATE TABLE IF NOT EXISTS `" . $this->table . "` (
			`client_id` int(11) NOT NULL,
			`registrant_id` varchar(10) NOT NULL,
			PRIMARY KEY (`client_id`),
			KEY `registrant_id` (`registrant_id`)
		) DEFAULT CHARSET=utf8 COMMENT='Local storage for Domreg contacts';
		");
	}

	public function find( $where ) {
		$query = select_query( $this->table, $this->fields, $where );
		if ( ! $query ) {
			throw new DomregRegistrantsTableException( "Failed to create select query @ find()" );
		}
		$result = mysql_fetch_assoc( $query );
		return $result ? array_filter( $result ) : false;
	}

	public function save( $item ) {
		if ( ! $item["client_id"] ) {
			throw new DomregRegistrantsTableException( "No client id supplied @ save()" );
		}
		$result = $this->find(array( "client_id" => $item["client_id"] ));
		if ( ! $result ) {
			return insert_query( $this->table, $item );
		}
		foreach ( $item as $i => $value ) {
			if ( $value != $result[$i] ) {
				return update_query( $this->table, $item, array(
					"client_id" => $item["client_id"]
				));
			}
		}
		return true;
	}

	public function getClientId( $registrant_id ) {
		$item = $this->find(array( "registrant_id" => $registrant_id ));
		if ( ! $item ) {
			return false;
		}
		return intval( $item["client_id"] );
	}

	public function getRegistrantId( $client_id ) {
		$item = $this->find(array( "client_id" => intval( $client_id ) ));
		if ( ! $item ) {
			return false;
		}
		return $item["registrant_id"];
	}

	public function setRegistrantId( $client_id, $registrant_id ) {
		return $this->save(array(
			"client_id" => intval( $client_id ),
			"registrant_id" => $registrant_id,
		));
	}

}



// --------------------------------------------------------
//  DomregRegistrant
//  Registrant functions
// --------------------------------------------------------

class DomregRegistrant {

	// Extracts registrant info from params variable
	public static function createFromWHMCSParams( $params, $registrant = array() ) {
		// Static fields
		$registrant["role"] = "registrant";

		// Default fields (when creating a contact)
		if ( ! empty( $params["id"] ) )
			$registrant["client_id"] = $params["id"];
		if ( ! empty( $params["firstname"] ) or ! empty( $params["lastname"] ) )
			$registrant["name"] = trim( $params["firstname"] . " " . $params["lastname"] );
		if ( ! empty( $params["address1"] ) or ! empty( $params["address2"] ) )
			$registrant["street"] = trim( $params["address1"] . " " . $params["address2"] );
		if ( ! empty( $params["city"] ) )
			$registrant["city"] = trim( $params["city"] );
		if ( ! empty( $params["country"] ) )
			$registrant["cc"] = strtolower( $params["country"] );
		if ( ! empty( $params["phonenumber"] ) )
			$registrant["voice"] = "+" . $params["phonecc"] . "." . $params["phonenumber"];
		if ( ! empty( $params["email"] ) )
			$registrant["email"] = strtolower( $params["email"] );
		if ( ! empty( $params["state"] ) )
			$registrant["sp"] = trim( $params["state"] );
		if ( ! empty( $params["postcode"] ) )
			$registrant["pc"] = trim( $params["postcode"] );
		if ( ! empty( $params["companyname"] ) )
			$registrant["org"] = trim( $params["companyname"] );
		
		// Additional fields (when editing contact)
		if ( ! empty( $params["Email"] ) )
			$registrant["email"] = $params["Email"];
		if ( ! empty( $params["Phone Number"] ) )
			$registrant["voice"] = $params["Phone Number"];
		if ( ! empty( $params["Street"] ) )
			$registrant["street"] = trim( $params["Street"] );
		if ( ! empty( $params["City"] ) )
			$registrant["city"] = trim( $params["City"] );
		if ( ! empty( $params["Region"] ) )
			$registrant["sp"] = trim( $params["Region"] );
		if ( ! empty( $params["Post code"] ) )
			$registrant["pc"] = trim( $params["Post code"] );
		if ( ! empty( $params["ZIP"] ) )
			$registrant["pc"] = trim( $params["ZIP"] );
		if ( ! empty( $params["Country code"] ) )
			$registrant["cc"] = trim( $params["Country code"] );

		return $registrant;
	}

}



// --------------------------------------------------------
//  Domreg API
// --------------------------------------------------------

class DomregAPIException extends Exception {}
class DomregAPIExecutorException extends Exception {
	public function __construct( $executor ) {
		$this->message = $executor->errorMsg;
	}
}

class DomregAPI {

	// ----- Properties -----
	public $username;
	public $password;

	public $supportContact;
	public $nsGroups = array();

	public $isTestMode = false;
	public $executor;


	// ----- Constructor -----

	public function __construct( $options = array() ) {
		foreach ( $options as $opt => $value ) {
			$this->{$opt} = $value;
		}

		// Require dependencies (EPP)
		$path = "/usr/share/php";
		set_include_path( get_include_path() . PATH_SEPARATOR . $path );
		require_once "PEAR.php";
		require_once "lib/Executor.class.php";
		require_once "lib/Xml2Array.class.php";
		require_once "pear/Net/EPP/Client.php";

		// Create EPP executor
		$this->executor = new Executor( array(
			"regid" => $this->username, "regpw" => $this->password,
			"host" => "epp." . ( $this->isTestMode ? "test." : "" ) . "domreg.lt",
			"port" => 700, "timeout" => 10, "ssl" => true
		));

		if ( ! $this->executor ) {
			throw new DomregAPIException( "Error initializing EPP executor." );
		}

		if ( ! $this->executor->EppLogin() ) {
			throw new DomregAPIException( "EPP error: " . $this->executor->errorMsg );
		}
	}


	// ----- Private methods -----

	// Response pass-through with error checking
	private function checkExecutorResponse( $response ) {
		if ( ! $response ) {
			throw new DomregAPIExecutorException( $this->executor );
		}
		return $response;
	}

	// Map nameservers to executor format
	private function nsListToExecutor( $ns ) {
		$ns = array_filter( $ns );
		foreach ( $ns as $value ) {
			$domreg_ns[ $value ] = array();
		}
		return $domreg_ns;
	}


	// ----- Public methods -----

	// Logout from EPP (should be called at the end to correctly close session)
	public function logout() {
		return $this->executor->EppLogout();
	}

	// Get registrant contact
	public function getRegistrant( $id ) {
		$response = $this->executor->EppContactInfo( $id );
		return $this->checkExecutorResponse( $response );
	}

	// Get registrant information from domain
	public function getRegistrantByDomain( $domain ) {
		$domain_info = $this->getDomainInfo( $domain );
		return $domain_info["registrant"];
	}

	// Save registrant contact
	public function updateRegistrant( $registrant ) {
		$response = $this->executor->EppContactUpdate( $registrant );
		return $this->checkExecutorResponse( $response );
	}

	// Create registrant contact
	public function createRegistrant( $registrant ) {
		$id = $this->executor->EppContactCreate( $registrant );
		return $this->checkExecutorResponse( $id );
	}

	// Register a new domain
	public function createDomain( $domain, $registrant, $ns = array(), $regperiod = 1 ) {
		$response = $this->executor->EppDomainCreate( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->supportContact,
			"ns" => $this->nsListToExecutor( $ns ),
			"period" => $regperiod,
			"onExpire" => "delete"
		));
		return $this->checkExecutorResponse( $response );
	}

	// Transfer domain to another registrar without changing ownership
	public function transferDomain( $domain, $registrant, $ns = array() ) {
		$response = $this->executor->EppDomainTransfer( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->supportContact,
			"ns" => $this->nsListToExecutor( $ns ),
			"onExpire" => "delete",
			"trType" => "transfer"
		));
		return $this->checkExecutorResponse( $response );
	}

	// Change domain ownership
	public function tradeDomain( $domain, $registrant, $ns = array() ) {
		$response = $this->executor->EppDomainTransfer( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->supportContact,
			"trType" => "trade"
		));
		return $this->checkExecutorResponse( $response );
	}

	// Renew domain (sets renew flag, doesn't actually renew anything)
	public function renewDomain( $domain ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(), array(), array(
			"onExpire" => "renew"
		));
		return $this->checkExecutorResponse( $response );
	}

	// Delete domain
	public function deleteDomain( $domain ) {
		$response = $this->executor->EppDomainDelete( $domain );
		return $this->checkExecutorResponse( $response );
	}

	// Get info about a domain
	public function getDomainInfo( $domain, $recursive = true ) {
		$response = $this->executor->EppDomainInfo( $domain );
		$this->checkExecutorResponse( $response );
		$data = array(
			"domain" => $response["domain:infData"]["domain:name"]["#text"],
			"registrant_id" => $response["domain:infData"]["domain:registrant"]["#text"],
			"contact_id" => $response["domain:infData"]["domain:contact"]["#text"],
			"status" => $response["domain:infData"]["domain:status"]["@s"],
			"on_expire" => $response["domain:infData"]["domain:onExpire"]["#text"],
			"ns" => array_filter( array(
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][0]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][1]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][2]["domain:hostName"]["#text"],
			)),
			"ns_groups" => array_filter( array(
				$response["domain:infData"]["domain:ns"]["domain:hostGroup"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostGroup"][0]["#text"],
			)),
			"date_created" => substr( $response["domain:infData"]["domain:crDate"]["#text"], 0, 10 ),
			"date_updated" => substr( $response["domain:infData"]["domain:upDate"]["#text"], 0, 10 ),
			"date_expires" => substr( $response["domain:infData"]["domain:exDate"]["#text"], 0, 10 ),
		);
		if ( $recursive ) {
			$registrant = $this->getRegistrant( $data["registrant_id"] );
			if ( ! $registrant ) {
				throw new DomregAPIException("Registrant not found");
			} else {
				$data["registrant"] = $registrant;
			}
			if ( count( $data["ns_groups"] ) > 0 ) {
				foreach ( $data["ns_groups"] as $ns_group ) {
					$ns = $this->getNsGroupInfo( $ns_group );
					$data["ns"] = array_merge( $ns, $data["ns"] );
				}
			}
		}
		return $data;
	}

	// Get nameservers in a particular ns group
	public function getNsGroupInfo( $ns_group ) {
		$response = $this->executor->EppNsGroupInfo( $ns_group );
		$this->checkExecutorResponse( $response );
		return array_filter( array(
			$response["nsgroup:ns"]["#text"],
			$response["nsgroup:ns"][0]["#text"],
			$response["nsgroup:ns"][1]["#text"],
			$response["nsgroup:ns"][2]["#text"],
		));
	}

	// Groups nameservers (returns groups and ungrouped nameservers)
	public function groupNs( $ns ) {
		$result = array(
			"ns_groups" => array(),
			"ns" => array_filter( $ns ),
		);
		foreach ( $this->nsGroups as $ns_group ) {
			$ns_group_info = $this->getNsGroupInfo( $ns_group );
			$ns_diff = array_diff( $result["ns"], $ns_group_info );
			if ( count( $result["ns"] ) > count( $ns_diff ) ) {
				$result["ns_groups"][] = $ns_group;
				$result["ns"] = $ns_diff;
			}
		}
		return $result;
	}

	// Get nameservers of a domain
	public function getNs( $domain, $ns_merging = true ) {
		$domain_info = $this->getDomainInfo( $domain, false );
		if ( $ns_merging and count( $domain_info["ns_groups"] ) > 0 ) {
			foreach ( $domain_info["ns_groups"] as $ns_group ) {
				$ns_group_info = $this->getNsGroupInfo( $ns_group );
				$domain_info["ns"] = array_merge( $ns_group_info, $domain_info["ns"] );
			}
		}
		return array(
			"ns_groups" => $domain_info["ns_groups"],
			"ns" => $domain_info["ns"],
		);
	}

	// Set nameservers for a domain in bulk
	public function setNs( $domain, $ns = array() ) {
		$ns1 = $this->groupNs( array_filter( $ns ) );
		$ns2 = $this->getNs( $domain, false );
		$ns_add = array(
			"ns_groups" => array_diff( $ns1["ns_groups"], $ns2["ns_groups"] ),
			"ns" => array_diff( $ns1["ns"], $ns2["ns"] ),
		);
		$ns_rem = array(
			"ns_groups" => array_diff( $ns2["ns_groups"], $ns1["ns_groups"] ),
			"ns" => array_diff( $ns2["ns"], $ns1["ns"] ),
		);
		// Map nameservers to executor format
		foreach ( $ns_add["ns"] as $value ) $ns_add["ns_domreg"][ $value ] = array();
		foreach ( $ns_rem["ns"] as $value ) $ns_rem["ns_domreg"][ $value ] = array();
		// Update nameservers
		$response = $this->executor->EppDomainUpdate( $domain, array(
			"ns_groups" => $ns_add["ns_groups"],
			"ns" => $ns_add["ns_domreg"]
		), array(
			"ns_groups" => $ns_rem["ns_groups"],
			"ns" => $ns_rem["ns_domreg"]
		));
		return $this->checkExecutorResponse( $response );
	}

	// Add a nameserver (with optional IP glue)
	public function addNs( $domain, $ns, $ip ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(
			"ns" => array( $ns => array( 4 => $ip ) )
		));
		return $this->checkExecutorResponse( $response );
	}

	// Delete a specified nameserver
	public function deleteNs( $domain, $ns ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(), array(
			"ns" => array( $ns => array() )
		));
		return $this->checkExecutorResponse( $response );
	}

	// Domreg message polling function (pretty useless, though)
	// TODO: Return message list
	// public function poll() {
	// 	$response = $this->executor->EppPoll("req");
	// 	$this->checkExecutorResponse( $response );
	// 	switch ( $response["RCode"] ) {
	// 		case "1301":
	// 			if ( $response["type"] == "global" and $response["type"] == "domain" ) {
	// 				$this->log( "poll_1301", $response );
	// 				$this->executor->EppPoll( "ack", $response["id"] );
	// 			}
	// 		break;
	// 		case "1300":
	// 			if ( $response["type"] == "global" and $response["type"] == "domain" ) {
	// 				$this->log( "poll_1300", $response );
	// 				$this->executor->EppPoll( "ack", $response["id"] );
	// 			}
	// 			$this->log( "poll", "Queue is empty" );
	// 		break;
	// 		case "1000":
	// 			$this->log( "poll", "Ack OK" );
	// 		break;
	// 		default:
	// 			$this->log( "poll_unknown", $response );
	// 		break;
	// 	}
	// 	return $this->response_ok();
	// }
	
}



// --------------------------------------------------------------------------
//  Domreg WHMCS model
// --------------------------------------------------------------------------

class DomregException extends Exception {}

class Domreg {

	// Quickie instance getter
	public static $instance = null;
	public static function getInstance() {
		if ( $instance === null ) {
			$params = getregistrarconfigoptions("domreg");
			return new Domreg( $params );
		}
		return self::$instance;
	}

	// ----- Properties -----

	public $params;
	public $registrants;
	public $api;

	public $whmcs_admin = "admin";

	public $log_to_console = false;
	public $log_to_whmcs = true;

	// ----- Constructor -----

	public function __construct( $params, $options = array() ) {
		self::$instance = $this;
		$this->params = $params;

		foreach ( $options as $opt => $value ) {
			$this->{$opt} = $value;
		}

		// Initialize API
		$this->api = new DomregAPI(array(
			"username" => $params["Username"],
			"password" => $params["Password"],
			"isTestMode" => ( $params["TestMode"] == "Enable" ),
			"supportContact" => $params["SupportContact"],
			"nsGroups" => array_filter(array(
				$params["NsGroup1"], $params["NsGroup2"],
				$params["NsGroup3"], $params["NsGroup4"],
			)),
		));

		// Initialize registrants table
		$this->registrants = new DomregRegistrantsTable(array(
			"isTestMode" => ( $params["TestMode"] == "Enable" ),
			"table" => $params["RegistrantsTable"],
		));
	}

	// ----- Methods -----

	// Generic log function
	public function log( $action, $object ) {
		if ( $this->log_to_whmcs and function_exists("logModuleCall") ) {
			logModuleCall( "domreg_class", $action, $this->params, $object );
		}
		if ( $this->log_to_console ) {
			echo "domreg: " . $action;
			if ( $object !== null ) echo ": " . var_export( $object, true );
			echo "\n";
		}
	}

	// Logically resolve correct registrant based off:
	//  * Existence of RN in Domreg
	//  * RN associated with a WHMCS account
	//  * RN associated with a domain
	//
	// Creates new registrant in case it wasn't found
	// Always returns some registrant object.
	public function requireRegistrant( $client_id, $domain = null ) {
		if ( $domain ) {
			try {
				$domain_info = $this->api->getDomainInfo( $domain );
			} catch ( DomregAPIExecutorException $e ) {}
		}
		if ( ! $domain_info ) {
			$registrant_id = $this->registrants->getRegistrantId( $client_id );
			if ( ! $registrant_id ) {
				$registrant = DomregRegistrant::createFromWHMCSParams( $this->params );
				$registrant["id"] = $this->api->createRegistrant( $registrant );
				$this->registrants->setRegistrantId( $client_id, $registrant["id"] );
			} else {
				$registrant = $this->api->getRegistrant( $registrant_id );
			}
		} else {
			$registrant = $domain_info["registrant"];
			$registrant_id = $this->registrants->getRegistrantId( $client_id );
			if ( ! $registrant_id ) {
				$this->registrants->setRegistrantId( $client_id, $registrant["id"] );
			}
		}
		logModuleCall( WHMCS_MODULE, __FUNCTION__, array(
			"client_id" => $client_id,
			"domain" => $domain,
		), array(
			"domain_info" => $domain_info,
			"registrant_id" => $registrant_id,
			"registrant" => $registrant,
		));
		return $registrant;
	}

}