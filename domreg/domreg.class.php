<?php

error_reporting( E_ERROR | E_WARNING | E_PARSE );
ini_set( "display_errors", 1 );


// --------------------------------------------------------------------------
//  Local database of Domreg registrants
// --------------------------------------------------------------------------

class DomregRegistrantsDB {

	public $table;

	public function __construct( $table ) {
		$this->table = $table;
		return full_query("
		CREATE TABLE IF NOT EXISTS `" . $table . "` (
			`id` varchar(10) NOT NULL COMMENT 'domreg id',
			`client_id` int(5) NOT NULL,
			`name` varchar(128) NOT NULL COMMENT 'full name',
			`org` varchar(128) DEFAULT NULL COMMENT 'organisation name',
			`email` varchar(128) NOT NULL,
			`street` text NOT NULL,
			`city` varchar(128) NOT NULL,
			`sp` varchar(128) DEFAULT NULL COMMENT 'province',
			`pc` varchar(6) DEFAULT NULL COMMENT 'postal code',
			`cc` varchar(4) NOT NULL COMMENT 'country code',
			`voice` varchar(16) NOT NULL COMMENT 'phone number',
			`fax` varchar(16) DEFAULT NULL,
			`role` enum('registrant') DEFAULT NULL COMMENT 'just registrant',
			PRIMARY KEY (`id`),
			UNIQUE KEY `client_id` (`client_id`)
		) DEFAULT CHARSET=utf8 COMMENT='Local storage for Domreg contacts';
		");
	}

	public function available_index( $contact ) {
		$field = null;
		foreach ( array( "id", "client_id" ) as $i ) {
			if ( isset( $contact[ $i ] ) ) {
				$field = $i;
				break;
			}
		}
		return $field;
	}

	public function find_registrant( $contact ) {
		$fields = "id,client_id,name,org,street,city,sp,pc,cc,voice,fax,email";
		$field = $this->available_index( $contact );
		$where = array( $field => $contact[ $field ] );
		$query = select_query( $this->table, $fields, $where );
		if ( ! $query ) return false;
		$result = mysql_fetch_assoc( $query );
		return $result ? array_filter( $result ) : false;
	}

	public function save_registrant( $contact ) {
		$field = $this->available_index( $contact );
		$result = $this->find_registrant( array( $field => $contact[ $field ] ) );
		if ( ! $result ) return insert_query( $this->table, $contact );
		foreach ( $contact as $key => $value ) {
			if ( $value != $result[$key] and $key != "role" ) {
				update_query( $this->table, $contact, array(
					$field => $contact[ $field ]
				));
				return "updated";
			}
		}
		return "unchanged";
	}

	public function remove_registrant( $contact = false ) {
		return full_query("
			DELETE FROM `" . $this->table ."`
			WHERE `id` LIKE '" . mysql_real_escape_string( $contact["id"] ) . "'
		");
	}

}



// --------------------------------------------------------------------------
//  Domreg class
// --------------------------------------------------------------------------

class Domreg {

	// ----- Instance tracking -----
	private static $instance;
	public static function getInstance() {
		return self::$instance;
	}

	// ----- Properties -----
	public $username;
	public $password;
	public $registrants_table = "domreg_registrants";
	public $whmcs_admin = "admin";
	public $default_support_contact;
	public $default_domain_group;

	public $is_testing = false;
	public $params;
	public $db;
	public $executor;
	public $error = false;

	public $log_to_console = false;
	public $log_to_whmcs = true;

	// ----- Construct -----
	public function __construct( $params, $options = array() ) {
		self::$instance = $this;
		$this->params = $params;

		if ( isset($params["TestMode"]) ) $this->is_testing = $params["TestMode"];
		if ( isset($params["RegistrantsTable"]) ) $this->registrants_table = $params["RegistrantsTable"];
		$this->username = $params["Username"];
		$this->password = $params["Password"];
		$this->default_support_contact = $params["SupportContact"];

		foreach ( $options as $opt => $value ) $this->{$opt} = $value;

		$table = $this->registrants_table . ( $this->is_testing ? "_test" : "" );
		$this->db = new DomregRegistrantsDB( $table );

		// Include dependencies
		$path = "/usr/share/php";
		set_include_path( get_include_path() . PATH_SEPARATOR . $path );
		require_once("PEAR.php");
		require_once("lib/Executor.class.php");
		require_once("lib/Xml2Array.class.php");
		require_once("pear/Net/EPP/Client.php");

		$this->executor = new Executor( array(
			"regid" => $this->username,
			"regpw" => $this->password,
			"host" => "epp." . ( $this->is_testing ? "test." : "" ) . "domreg.lt",
			"port" => 700, "timeout" => 10, "ssl" => true
		));

		if ( ! $this->executor ) {
			$this->error = "Error initializing EPP executor.";
		}

		if ( ! $this->executor->EppLogin() ) {
			$this->error = "EPP error: " . $this->executor->errorMsg;
		}
	}

	// ----- Methods -----

	public function logout() {
		return $this->executor->EppLogout();
	}

	public function executor_error() {
		$this->error = $this->executor->errorMsg;
		return false;
	}

	public function executor_ok() {
		$this->error = false;
		return true;
	}

	public function log( $action, $obj ) {
		if ( $this->log_to_whmcs and function_exists("logModuleCall") ) {
			logModuleCall( "domreg_class", $action, $this->params, $obj );
		}
		if ( $this->log_to_console ) {
			echo "domreg: " . $action;
			if ( $obj !== null ) echo ": " . var_export( $obj, true );
			echo "\n";
		}
	}



	// Convert phone number to domreg format
	public function convert_phone_number( $number, $cc = "lt" ) {
		$number = preg_replace( "(\+370\.370)", "370", $number );
		$number = preg_replace( "(\+370\.86)", "3706", $number );
		// If number looks like local lithuanian, change it to int. format
		if ( preg_match( "/8.{8}/", $number ) ) {
			$number = "370" . substr( $number, -8 );
		}
		// Int. number pattern
		$pattern = "/(9[976]\d|8[987530]\d|6[987]\d|5[90]\d|42\d|3[875]\d" .
			"|2[98654321]\d|9[8543210]|8[6421]|6[6543210]|5[87654321]" .
			"|4[987654310]|3[9643210]|2[70]|7|1)(.{7,15})/";
		$is_matching = preg_match( $pattern, $number, $matches );
		if ( ! $is_matching or count( $matches ) != 3 ) return false;
		return "+" . $matches[1] . "." . $matches[2];
	}



	// Extracts registrant info from params variable
	public function params_to_registrant( $params, $registrant = array() ) {
		// Static fields
		$registrant["role"] = "registrant";

		$this->log( "params_to_registrant", array(
			"registrant" => $registrant,
			"regdata" => $params
		) );

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
			$registrant["voice"] = $this->convert_phone_number( $params["phonenumber"] );
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
			$registrant["voice"] = $this->convert_phone_number( $params["Phone Number"] );
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



	// Synchronizes registrant with local database and domreg
	// Returns the most relevant registrant's data
	public function sync_registrant( $registrant = null ) {
		if ( ! $registrant ) {
			// Just assume registrant is the one in params variable
			$registrant = $this->params_to_registrant( $this->params );
		}

		$result = $this->db->find_registrant( $registrant );
		if ( ! $result ) {
			$registrant["id"] = $this->executor->EppContactCreate( $registrant );
			if ( ! $registrant["id"] ) return $this->executor_error();
			$status = $this->db->save_registrant( $registrant );
		} else {
			$domreg_contact = $this->executor->EppContactInfo( $result["id"] );
			if ( ! $domreg_contact ) {
				$registrant["id"] = $this->executor->EppContactCreate( $registrant );
			} else {
				$registrant["id"] = $result["id"];
			}
			$status = $this->db->save_registrant( $registrant );
			if ( $status === "updated" ) {
				$response = $this->executor->EppContactUpdate( $registrant );
				if ( ! $response ) $this->executor_error();
			}
		}

		$this->error = false;
		return $registrant;
	}



	// Only updates registrant in local database and domreg
	// Returns true on success, false if not found or on any executor error
	public function update_registrant( $registrant = null ) {
		if ( ! $registrant ) {
			// Just assume registrant is the one in params variable
			$registrant = $this->params_to_registrant( $this->params );
		}

		$result = $this->db->find_registrant( $registrant );
		if ( ! $result ) {
			$this->error = "Registrant not found";
			return false;
		} else {
			$status = $this->db->save_registrant( $registrant );
			if ( $status === "updated" ) {
				$response = $this->executor->EppContactUpdate( $registrant );
				if ( ! $response ) return $this->executor_error();
			}
		}

		return $this->executor_ok();
	}



	public function create_domain( $domain, $registrant, $ns = array() ) {
		// Map nameservers to executor format
		$ns = array_filter( $ns );
		foreach ( $ns as $value ) $domreg_ns[ $value ] = array();
		// Create domain
		$response = $this->executor->EppDomainCreate( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->default_support_contact,
			"ns" => $domreg_ns,
			"onExpire" => "delete"
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}

	public function transfer_domain( $domain, $registrant, $ns = array() ) {
		// Map nameservers to executor format
		$ns = array_filter( $ns );
		foreach ( $ns as $value ) $domreg_ns[ $value ] = array();
		// Transfer domain
		$response = $this->executor->EppDomainTransfer( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->default_support_contact,
			"ns" => $domreg_ns,
			"onExpire" => "delete",
			"trType" => "transfer"
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}

	public function trade_domain( $domain, $registrant, $ns = array() ) {
		$response = $this->executor->EppDomainTransfer( array(
			"name" => $domain,
			"registrant" => $registrant["id"],
			"contact" => $this->default_support_contact,
			"trType" => "trade"
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}

	public function renew_domain( $domain ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(), array(), array(
			"onExpire" => "renew"
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}

	public function delete_domain( $domain ) {
		$response = $this->executor->EppDomainDelete( $domain );
		return $response ? $this->executor_ok() : $this->executor_error();
	}



	public function get_domain_info( $domain, $recursive = true ) {
		$response = $this->executor->EppDomainInfo( $domain );
		if ( ! $response ) return $this->executor_error();
		$data = array(
			"domain" => $response["domain:infData"]["domain:name"]["#text"],
			"on_expire" => $response["domain:infData"]["domain:onExpire"]["#text"],
			"registrant_id" => $response["domain:infData"]["domain:registrant"]["#text"],
			"contact_id" => $response["domain:infData"]["domain:contact"]["#text"],
			"ns" => array(
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][0]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][1]["domain:hostName"]["#text"],
				$response["domain:infData"]["domain:ns"]["domain:hostAttr"][2]["domain:hostName"]["#text"]
			),
			# TODO: needs more info fields
		);
		if ( $recursive ) {
			$registrant = $this->db->find_registrant( array( "id" => $data["registrant_id"] ) );
			if ( ! $registrant ) return $data;
			$data["registrant"] = $registrant;
		}
		return $data;
	}



	public function get_ns_group( $domain ) {
		// TODO: NS groups
	}

	public function set_ns_group( $domain, $ns ) {
		// TODO: NS groups
	}



	public function get_ns_servers( $domain ) {
		$response = $this->get_domain_info( $domain, false );
		if ( ! $response ) return $this->executor_error();
		return array_filter( $response["ns"] );
	}

	public function set_ns_servers( $domain, $ns = array() ) {
		$ns = array_filter( $ns );
		$ns_existing = $this->get_ns_servers( $domain );
		$ns_add = array_diff( $ns, $ns_existing );
		$ns_rem = array_diff( $ns_existing, $ns );
		// Map nameservers to executor format
		foreach ( $ns_add as $value ) $domreg_ns_add[ $value ] = array();
		foreach ( $ns_rem as $value ) $domreg_ns_rem[ $value ] = array();
		// Update nameservers
		$response = $this->executor->EppDomainUpdate( $domain, array(
			"ns" => $domreg_ns_add
		), array(
			"ns" => $domreg_ns_rem
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}



	public function register_ns( $domain, $ns, $ip ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(
			"ns" => array( $ns => array( 4 => $ip ) )
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}

	public function delete_ns( $domain, $ns ) {
		$response = $this->executor->EppDomainUpdate( $domain, array(), array(
			"ns" => array( $ns => array() )
		));
		return $response ? $this->executor_ok() : $this->executor_error();
	}



	public function poll() {
		$response = $this->executor->EppPoll("req");
		if ( ! $response ) return $this->executor_error();
		switch ( $response["RCode"] ) {
			case "1301":
				if ( $response["type"] == "global" and $response["type"] == "domain" ) {
					$this->log( "poll_1301", $response );
					$this->executor->EppPoll( "ack", $response["id"] );
				}
			break;
			case "1300":
				if ( $response["type"] == "global" and $response["type"] == "domain" ) {
					$this->log( "poll_1300", $response );
					$this->executor->EppPoll( "ack", $response["id"] );
				}
				$this->log( "poll", "Queue is empty" );
			break;
			case "1000":
				$this->log( "poll", "Ack OK" );
			break;
			default:
				$this->log( "poll_unknown", $response );
			break;
		}
		return $this->executor_ok();
	}
	
}