<?php

class Executor{

var $regid="";
var $regpw="";
var $host = 'localhost';
var $port = 700;
var $timeout = 10;
var $ssl = true;
var $debug = false;
var $epp=null;
var $parser=null;
var $error=false;
var $errorMsg="";
var $debuginfo=array();
var $data=array();
var $TransId=null;
var $status=array();

	function Executor($params=array()){
		if (sizeof($params)>0){
			if (isset($params['regid'])) $this->regid=$params['regid'];
			else {
				$this->errorMsg = "No regid...app frozen!";
				$this->error=true;
				return false;
			}
			if (isset($params['regpw'])) $this->regpw=$params['regpw']; else die("No regpw...app frozen!");
			if (isset($params['host'])) $this->host=$params['host'];
			if (isset($params['port'])) $this->port=$params['port'];
			if (isset($params['timeout'])) $this->timeout=$params['timeout'];
			if (isset($params['ssl'])) $this->ssl=$params['ssl'];
		}else{
			$this->errorMsg = "No parameters...app frozen!";
			$this->error=true;
			return false;
		}
		$this->InitEppClient();
		$this->InitXmlParser();
		$this->SpoofTransactionId();
		return true;
	}

	function SpoofTransactionId(){
		$time = time();
		$this->TransId=$this->regid.$time;
	}

	function InitEppClient(){
		$this->epp = &new Net_EPP_Client();
	}

	function InitXmlParser(){
		$this->parser = &new xml2array();
	}

	function ClearData(){
		unset($this->data);
		$this->data=array();
	}

	function ErrorRaise($Response){
		if (is_object($Response)){
			if (isset($Response->message)){
				$this->errorMsg = $Response->message;
				$this->error=true;
				$this->debuginfo = $Response->backtrace;
				return false;
			}
		}
		return true;
	}

	function MakeError($Msg){
		$this->error=true;
		$this->errorMsg=$Msg;
	}

	function UnsetData(){
		$this->data = array();
	}

	function EppConnect(){
		$host=$this->host;
		$port=$this->port;
		$timeout=$this->timeout;
		$ssl=$this->ssl;
		$res = $this->epp->connect($host, $port, $timeout, $ssl);
		//dump($res);
		if (!$this->ErrorRaise($res)) return false;
		$this->resource['EppConnect']=$res;
		return true;
	}

	function EppDisconnect(){
		$res=$this->epp->disconnect();
		if ($res==1) {
			//unset($this->epp);
			return true;
		}
		$this->error=true;
		$this->errorMsg="Can not disconnect!";
		return false;
	}

	function EppIsConnected(){
		if (is_object($this->epp) && isset($this->epp->socket)) return true;
		return false;
	}


	function GetResponse(){
		$Response=$this->epp->getFrame();

		if (trim($Response)==""){
			$this->MakeError('No Response from server!');
			return false;
		}

		if(!$this->parser->SetInput($Response)){
			$this->MakeError("Unable to parse response to xml2array");
			return false;
		}

		if (!$this->parser->compile()){
			$this->MakeError("Unable to compile response to xml2array");
			return false;
		}
		$Response = $this->parser->data['epp']['response'];
		unset($this->parser->data);
		return $Response;
	}

	function GetCode($Response){
		return 	$Response['result']['@code'];
	}

	function GetMsg($Response){
		return 	$Response['result']['msg']['#text'];
	}
	function GetMsgQcount($Response){
		return 	$Response['msgQ']['@count'];
	}
	function GetMsgQID($Response){
		return 	$Response['msgQ']['@id'];
	}

	function EppLogin(){
		if (!$this->EppIsConnected()){
			if (!$this->EppConnect()) return false;
		}
		$cmd="
		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
				xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
				xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
				epp-1.0.xsd\">
				<command>
			 		<login>
						<clID>$this->regid</clID>
						<pw>$this->regpw</pw>
						<options>
						  <version>1.0</version>
						  <lang>lt</lang>
						</options>
						<svcs>
							<objURI>http://www.domreg.lt/epp/xml/domreg-domain-1.0</objURI>
						</svcs>
					</login>
			 		<clTRID>$this->TransId</clTRID>
				</command>
			</epp>
		";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->status['login']=1;
		return true;
	}

	function EppLogout(){
		if ($this->EppIsLoggedIn()){
			$cmd="

        		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
              xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
              xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
              epp-1.0.xsd\">
					<command>
						<logout/>
            		<clTRID>$this->TransId</clTRID>
					</command>
				</epp>
			";
			$res=$this->epp->sendFrame($cmd);
			$this->status['login']=0;

		}
	}

	function EppIsLoggedIn(){
		if (isset($this->status['login']) && $this->status['login']==1) return true;
		return false;
	}

	function EppContactInfo($contact_id=""){
			if (!$this->EppIsLoggedIn()){
				if (!$this->EppLogin()) return false;
			}
			if(trim($contact_id)=="") {
				$this->MakeError('No contact id supplied!');
				return false;
			}
			$cmd="
			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\">
				<command>
					<info>
						<contact:info xmlns:contact=\"http://www.domreg.lt/epp/xml/domreg-contact-1.0\">
							<contact:id>$contact_id</contact:id>
						</contact:info>
					</info>
				</command>
			</epp>
		";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return $this->data['contact:infData'];

	}

	function EppContactCreate($Cdata){

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$cmd="
			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\">
				<command>
					<create>
						<contact:create
							xmlns:contact=\"http://www.domreg.lt/epp/xml/domreg-contact-1.0\">
							<contact:postalInfo type=\"loc\">
								<contact:name>{$Cdata['name']}</contact:name>";
								if (isset($Cdata['org'])) $cmd .= "\t\t\t\t\t\t\t\t<contact:org>{$Cdata['org']}</contact:org>";
								$cmd .= "\t\t\t\t\t\t\t\t<contact:addr>
									<contact:street>{$Cdata['street']}</contact:street>
									<contact:city>{$Cdata['city']}</contact:city>";
									if (isset($Cdata['sp'])) $cmd .= "\t\t\t\t\t\t\t\t<contact:sp>{$Cdata['sp']}</contact:sp>";
									$cmd .= "\t\t\t\t\t\t\t\t<contact:pc>{$Cdata['pc']}</contact:pc>
									<contact:cc>{$Cdata['cc']}</contact:cc>
								</contact:addr>
							</contact:postalInfo>
							<contact:voice>{$Cdata['voice']}</contact:voice>";
							if (isset($Cdata['fax'])) $cmd .= "\t\t\t\t\t\t<contact:fax>{$Cdata['fax']}</contact:fax>";
							$cmd .= "\t\t\t\t\t\t<contact:email>{$Cdata['email']}</contact:email>
							<contact:role>{$Cdata['role']}</contact:role>
						</contact:create>
					</create>
			 		<clTRID>$this->TransId</clTRID>
				</command>
			</epp>
		";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
	 	if ($this->debug) echo "EppContactCreate: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];

		return $this->data['contact:creData']['contact:id']['#text'];
	}

	function EppContactDelete($contactid){

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}
		$cmd = "
		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\">
           <command>
             <delete>
               <contact:delete
                xmlns:contact=\"http://www.domreg.lt/epp/xml/domreg-contact-1.0\">
                 <contact:id>$contactid</contact:id>
               </contact:delete>
             </delete>
             <clTRID>$this->TransId</clTRID>
           </command>
         </epp>
		";

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
        if ($this->debug) echo "EppContactDelete: $RCode -> $RMsg\n\n";

		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

	function EppContactUpdate($Cdata){
		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}
		$cmd="
			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\">
				<command>
					<update>
						<contact:update
							xmlns:contact=\"http://www.domreg.lt/epp/xml/domreg-contact-1.0\">
							<contact:id>{$Cdata['id']}</contact:id>
							<contact:chg>
								<contact:postalInfo type=\"loc\">";
									if (isset($Cdata['name']))	$cmd .= "<contact:name>{$Cdata['name']}</contact:name>";
									if (isset($Cdata['org'])) $cmd .= "<contact:org>{$Cdata['org']}</contact:org>";
									$cmd .= "<contact:addr>";
										if (isset($Cdata['street'])) $cmd .= "<contact:street>{$Cdata['street']}</contact:street>";
										if (isset($Cdata['city'])) $cmd .= "<contact:city>{$Cdata['city']}</contact:city>";
										if (isset($Cdata['sp'])) $cmd .= "<contact:sp>{$Cdata['sp']}</contact:sp>";
										if (isset($Cdata['pc']))$cmd .= "<contact:pc>{$Cdata['pc']}</contact:pc>";
										if (isset($Cdata['cc'])) $cmd .= "<contact:cc>{$Cdata['cc']}</contact:cc>";
									$cmd .= "</contact:addr>
								</contact:postalInfo>";
								if (isset($Cdata['voice'])) $cmd .= "<contact:voice>{$Cdata['voice']}</contact:voice>";
								if (isset($Cdata['fax'])) $cmd .= "\t\t\t\t\t\t<contact:fax>{$Cdata['fax']}</contact:fax>";
								if (isset($Cdata['email'])) $cmd .= "\t\t\t\t\t\t<contact:email>{$Cdata['email']}</contact:email>";
							$cmd .= "</contact:chg>
						</contact:update>
					</update>
			 		<clTRID>$this->TransId</clTRID>
				</command>
			</epp>
		";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		if ($this->debug) echo "EppContactUpdate: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}


	function EppDomainInfo($domain){
		if (empty($domain)){
			$this->MakeError('No domain name supplied!');
			return false;
		}
		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$cmd="
     <epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
		<command>
			<info>
				<domain:info xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
					<domain:name>$domain</domain:name>
				</domain:info>
			</info>
			<clTRID>$this->TransId</clTRID>
		</command>
	</epp>
		";

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
        if ($this->debug) echo "EppDomainInfo: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return $this->data;

	}

	function EppDomainCheck($domain){

		if (!$this->EppIsLoggedIn()){
			echo "NO LOGIN\n";
			if (!$this->EppLogin()) return false;
		}
		if(trim($domain)=="") {
			$this->MakeError('No domain supplied!');
			return false;
		}
		$cmd="
<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
	<command>
	<check>
		<domain:check xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
			<domain:name>$domain</domain:name>
		</domain:check>
	</check>
	<clTRID>$this->TransId</clTRID>
	</command>
</epp>
";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		if ($this->debug) echo "EppDomainCheck: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];

		return true;
	}

	function EppIsDomainAvailable($domain){
		if (!$this->EppDomainCheck($domain)) return false;
		if ($this->data['domain:chkData']['domain:cd']['domain:name']['@avail']==1) {
			$avail='yes';
		}else{
			$avail='no';
		}
		return $avail;
	}

	function EppDomainCreate($data=array()){
		if (!isset($data['name']) || empty($data['name'])){
			$this->MakeError('No domain name supplied!');
			return false;
		}

		if (!isset($data['registrant']) || empty($data['registrant'])){
			$this->MakeError('No registrant supplied!');
			return false;
		}
		if (!isset($data['contact']) || empty($data['contact'])){
			$this->MakeError('No technical contact supplied!');
			return false;
		}
		if (!isset($data['onExpire']) || empty($data['onExpire'])){
			$this->MakeError('No onExpire supplied!');
			return false;
		}
		if (isset($data['ns']) && count($data['ns']) == 0) {
			$this->MakeError('Empty NS array supplied!');
			return false;
		}
		if (isset($data['ns']) && count($data['ns']) > 13) {
			$this->MakeError('Too large NS array supplied!');
			return false;
		}
		$cmd="
		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
			<command>
				<create>
					<domain:create xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
					<domain:name>{$data['name']}</domain:name>
					<domain:onExpire>{$data['onExpire']}</domain:onExpire>";
					if (isset($data['ns']))  {
						$cmd .= "<domain:ns>";
						foreach($data['ns'] as $name => $addr) {
							$cmd .= "<domain:hostAttr>";
							$cmd .= "<domain:hostName>$name</domain:hostName>";
							if (isset($addr[4])) $cmd .= "<domain:hostAddr ip=\"v4\">{$addr[4]}</domain:hostAddr>";
							if (isset($addr[6])) $cmd .= "<domain:hostAddr ip=\"v6\">{$addr[6]}</domain:hostAddr>";
							$cmd .= "</domain:hostAttr>";
						}
					$cmd .= "</domain:ns>";
					}
					$cmd .= "<domain:registrant>{$data['registrant']}</domain:registrant>
					<domain:contact>{$data['contact']}</domain:contact>
					</domain:create>
				</create>
				<clTRID>$this->TransId</clTRID>
			</command>
		</epp>
		";

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
        if ($this->debug) echo "EppDomainCreate: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

	function EppDomainTransfer($data=array()){
		if (!isset($data['name']) || empty($data['name'])){
			$this->MakeError('No domain name supplied!');
			return false;
		}

		if (!isset($data['registrant']) || empty($data['registrant'])){
			$this->MakeError('No registrant supplied!');
			return false;
		}

		if (!isset($data['contact']) || empty($data['contact'])){
			$this->MakeError('No technical contact supplied!');
			return false;
		}
		
		if (!isset($data['trType']) || empty($data['trType'])){
			$this->MakeError('No onExpire supplied!');
			return false;
		}

		$cmd="
		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
			<command>
				<transfer op=\"request\">
					<domain:transfer xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
					<domain:name>{$data['name']}</domain:name>
					<domain:trType>{$data['trType']}</domain:trType>
					<domain:registrant>{$data['registrant']}</domain:registrant>
					<domain:contact>{$data['contact']}</domain:contact>
					</domain:transfer>
				</transfer>
				<clTRID>$this->TransId</clTRID>
			</command>
		</epp>
		";

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
        if ($this->debug) echo "EppDomainCreate: $RCode -> $RMsg\n\n";
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

    function EppPoll($op=null,$msgid=null) {
    $cmd = "
    <epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
		xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
		xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">
		<command>";
			if ($op == "req") $cmd .= "<poll op=\"$op\"/>";
			elseif ($op == "ack") $cmd .= "<poll op=\"$op\" msgID=\"$msgid\"/>";
			$cmd .= "<clTRID>$this->TransId</clTRID>
		</command>
	</epp>";
	if (!$this->EppIsLoggedIn()){
		if (!$this->EppLogin()) return false;
	}
	$res=$this->epp->sendFrame($cmd);
	$Response = $this->GetResponse();
	$RCode = $this->GetCode($Response);
	$RMsg = $this->GetMsg($Response);
	$RMQ = $this->GetMsgQcount($Response);
	$RMid= $this->GetMsgQID($Response);
    if ($this->debug) echo "EppPoll: $RCode -> $RMsg\n\n";
	$this->errorMsg = $RMsg;
	if ($RCode>=2000 || $RCode<1000){
		$this->error=true;
		return false;
	}
	$this->data = $Response['resData'];
	$r['RCode'] = $RCode;
	$r['queue'] = $RMQ;
	$r['id'] = $RMid;
	$r['type'] = $this->data['event:eventData']['event:obType']['#text'];
	$r['object'] = $this->data['event:eventData']['event:object']['#text'];
	$r['notice'] = $this->data['event:eventData']['event:notice']['#text'];
	return $r;
    }

	function EppDomainUpdate($domain="", $add=array(), $rem=array(), $chg=array()){
		if (empty($domain)){
			$this->MakeError('No domain name');
			return false;
		}


		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$cmd="
		<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
		<command>
		<update>
			<domain:update xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
				<domain:name>$domain</domain:name>";
				if ( count($add) > 0 ) {
					$cmd .= "<domain:add>";
					if ( isset($add['ns']) ) {
						$cmd .= "<domain:ns>";
						foreach( $add['ns'] as $name => $addr ) {
							$cmd .= "<domain:hostAttr>";
							$cmd .= "<domain:hostName>$name</domain:hostName>";
							if (isset($addr[4])) $cmd .= "<domain:hostAddr ip=\"v4\">{$addr[4]}</domain:hostAddr>";
							if (isset($addr[6])) $cmd .= "<domain:hostAddr ip=\"v6\">{$addr[6]}</domain:hostAddr>";
							$cmd .= "</domain:hostAttr>";
						}
						$cmd .= "</domain:ns>";
					}
					if  (isset($add['contact'])) $cmd .= "<domain:contact>{$add['contact']}</domain:contact>";
					$cmd .= "</domain:add>";
				}
				if ( count($rem) > 0 ) {
					$cmd .= "<domain:rem>";
					if ( isset($rem['ns']) ) {
						$cmd .= "<domain:ns>";
						foreach( $rem['ns'] as $name => $addr ) {
							$cmd .= "<domain:hostAttr>";
							$cmd .= "<domain:hostName>$name</domain:hostName>";
							if (isset($addr[4])) $cmd .= "<domain:hostAddr ip=\"v4\">{$addr[4]}</domain:hostAddr>";
							if (isset($addr[6])) $cmd .= "<domain:hostAddr ip=\"v6\">{$addr[6]}</domain:hostAddr>";
							$cmd .= "</domain:hostAttr>";
						}
						$cmd .= "</domain:ns>";
					}
					if  (isset($rem['contact'])) $cmd .= "<domain:contact>{$rem['contact']}</domain:contact>";
					$cmd .= "</domain:rem>";
				}
				if ( count($chg) > 0 ) {
					$cmd .= "<domain:chg>";
					if ( isset( $chg["onExpire"] ) ) $cmd .= "<domain:onExpire>" . $chg["onExpire"] . "</domain:onExpire>";
					$cmd .= "</domain:chg>";
				}
			$cmd .="</domain:update>
		</update>
		<clTRID>$this->TransId</clTRID>
		</command>
		</epp>
		";

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);

		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;

	}

	function EppDomainDelete($domain=null){
		if (empty($domain)){
			$this->MakeError('No domain name');
			return false;
		}
		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}
		$cmd="
         	<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
				<command>
					<delete>
						<domain:delete xmlns:domain=\"http://www.domreg.lt/epp/xml/domreg-domain-1.0\">
							<domain:name>$domain</domain:name>
						</domain:delete>
					</delete>
				</command>
			</epp>
			";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}


	function EppRemoveHostFromDomain($ns="",$domain=""){
		if (empty($domain)){
			$this->MakeError('Domeniu nespecificat sau invalid');
			return false;
		}
		if (empty($ns)){
			$this->MakeError('Nameserver nespecificat sau invalid');
			return false;
		}

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$cmd="


         <epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
              xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
              xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
              epp-1.0.xsd\">
           <command>
             <update>
               <domain:update
                xmlns:domain=\"urn:ietf:params:xml:ns:domain-1.0\"
                xsi:schemaLocation=\"urn:ietf:params:xml:ns:domain-1.0
                domain-1.0.xsd\">
                 <domain:name>$domain</domain:name>
                 <domain:rem>
                   <domain:ns>$ns</domain:ns>
                 </domain:rem>
               </domain:update>
             </update>
             <clTRID>$this->TransId</clTRID>
           </command>
         </epp>

		";

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);

		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;

	}
}

function EppHostCheck($nameserver){
		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		if(trim($nameserver)=="") {
			$this->MakeError('No nameserver supplied!');
			return false;
		}
		$cmd="

			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
				xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
				xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
				epp-1.0.xsd\">
				<command>
					<check>
	               <host:check
                		xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\"
                		xsi:schemaLocation=\"urn:ietf:params:xml:ns:host-1.0
                		host-1.0.xsd\">
                		<host:name>$nameserver</host:name>
               	</host:check>
	             </check>
	             <clTRID>$this->TransId</clTRID>
				</command>
			</epp>
		";
		$res=$this->epp->sendFrame($cmd);

		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);

		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];

		return true;
	}

	function EppIsHostAvailable($nameserver){
		if (!$this->EppHostCheck($nameserver)) return false;
		if ($this->data['host:chkData']['host:cd']['host:name']['@avail']==1) {
			$avail='yes';
		}else{
			$avail='no';
		}
		return $avail;
	}

	function EppHostInfo($nameserver){
		if (!$this->EppIsLoggedIn()){
				if (!$this->EppLogin()) return false;
			}
			if(trim($nameserver)=="") {
				$this->MakeError('No nameserver supplied!');
				return false;
			}
			$cmd="

			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
				xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
				xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
				epp-1.0.xsd\">
				<command>
			 		 <info>
	               <host:info
	                xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\"
	                xsi:schemaLocation=\"urn:ietf:params:xml:ns:host-1.0
	                host-1.0.xsd\">
	                 <host:name>$nameserver</host:name>
	               </host:info>
            	 </info>
			 		<clTRID>$this->TransId</clTRID>
				</command>
			</epp>

		";
		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);

		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return $this->data['host:infData'];
	}

	function EppHostCreate($hostname, $hostip_v4="", $hostip_v6=""){
		if (empty($hostname)) {
			$this->MakeError('No host name supplied!');
			return false;
		}
		if (trim($hostip_v4)!=""){
			$hostip_v4_xml = "<host:addr ip=\"v4\">$hostip_v4</host:addr>";
		}
		if (trim($hostip_v6)!=""){
			$hostip_v6_xml = "<host:addr ip=\"v6\">$hostip_v6</host:addr>";
		}
		$cmd="

			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
              xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
              xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
              epp-1.0.xsd\">
           <command>
             <create>
               <host:create
                xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\"
                xsi:schemaLocation=\"urn:ietf:params:xml:ns:host-1.0
                host-1.0.xsd\">
                 <host:name>$hostname</host:name>
                 $hostip_v4_xml
                 $hostip_v6_xml
               </host:create>
             </create>
             <clTRID>$this->TransId</clTRID>
           </command>
         </epp>

		";
		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();
		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

	function EppHostDelete($hostname){

		if (empty($hostname)) {
			$this->MakeError('No domain name supplied!');
			return false;
		}

		$cmd = "

			 <epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
              xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
              xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd\">
	           <command>
	             <delete>
	               <host:delete
	                xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\" xsi:schemaLocation=\"urn:ietf:params:xml:ns:host-1.0 host-1.0.xsd\">
	                 <host:name>$hostname</host:name>
	               </host:delete>
	             </delete>
	             <clTRID>$this->TransId</clTRID>
	           </command>
	         </epp>

		";

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

	function EppHostUpdate($hostname, $data){
		if (empty($hostname)) {
			$this->MakeError('No hostname supplied!');
			return false;
		}
		if (!isset($data['command']) || $data['command']=="") {
			$this->MakeError('No command supplied!');
			return false;
		}

		$hostip_v4_xml = $hostip_v6_xml = $status_xml = $hostname_xml = "";
		switch ($data['command']) {

			case 'add':
				if (trim($data['ip_v4'])!=""){
					$hostip_v4_xml = "<host:addr ip=\"v4\">{$data['ip_v4']}</host:addr>";
				}
				if (trim($data['ip_v6'])!=""){
					$hostip_v6_xml = "<host:addr ip=\"v6\">{$data['ip_v6']}</host:addr>";
				}
				if (trim($data['status'])!="") {
					$status_xml = " <host:status s=\"{$data['status']}\"/>";
				}
				break;
			case 'rem':
				if (trim($data['ip_v4'])!=""){
					$hostip_v4_xml = "<host:addr ip=\"v4\">{$data['ip_v4']}</host:addr>";
				}
				if (trim($data['ip_v6'])!=""){
					$hostip_v6_xml = "<host:addr ip=\"v6\">{$data['ip_v6']}</host:addr>";
				}
				if (trim($data['status'])!="") {
					$status_xml = " <host:status s=\"{$data['status']}\"/>";
				}
				break;
			case 'chg':
				if (!isset($data['hostname']) || $data['hostname']=="") {
					$this->MakeError('Second hostname not supplied!');
					return false;
				}
				$hostname_xml = "<host:name>{$data['hostname']}</host:name>";
				break;
			default:
				$this->MakeError('Invalid command supplied!');
				return false;
				break;
		}


		$cmd="

			<epp xmlns=\"urn:ietf:params:xml:ns:epp-1.0\"
              xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
              xsi:schemaLocation=\"urn:ietf:params:xml:ns:epp-1.0
              epp-1.0.xsd\">
           <command>
             <update>
               <host:update
                xmlns:host=\"urn:ietf:params:xml:ns:host-1.0\"
                xsi:schemaLocation=\"urn:ietf:params:xml:ns:host-1.0
                host-1.0.xsd\">
                 <host:name>$hostname</host:name>
                 <host:{$data['command']}>
                 	$hostip_v4_xml
					$hostip_v6_xml
					$status_xml
					$hostname_xml
                 </host:{$data['command']}>
               </host:update>
             </update>
             <clTRID>$this->TransId</clTRID>
           </command>
         </epp>

		";

		if (!$this->EppIsLoggedIn()){
			if (!$this->EppLogin()) return false;
		}

		$res=$this->epp->sendFrame($cmd);
		$Response = $this->GetResponse();

		$RCode = $this->GetCode($Response);
		$RMsg = $this->GetMsg($Response);
		$this->errorMsg = $RMsg;
		if ($RCode>=2000 || $RCode<1000){
			$this->error=true;
			return false;
		}
		$this->data = $Response['resData'];
		return true;
	}

?>
