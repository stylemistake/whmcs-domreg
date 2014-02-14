<?php

	/*	EPP Client class for PHP, Copyright 2005 CentralNic Ltd
		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License as published by
		the Free Software Foundation; either version 2 of the License, or
		(at your option) any later version.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
	*/

	/**
	* A simple client class for the Extensible Provisioning Protocol (EPP)
	* @package Net_EPP_Client
	* @version 0.0.2
	* @author Gavin Brown <gavin.brown@nospam.centralnic.com>
	*/

	require_once('PEAR.php');

	$GLOBALS[Net_EPP_Client_Version] = '0.0.1';

	/**
	* A simple client class for the Extensible Provisioning Protocol (EPP)
	* @package Net_EPP_Client
	*/
	class Net_EPP_Client {

		/**
		* @var resource the socket resource, once connected
		*/
		var $socket;

		/**
		* Establishes a connect to the server
		* This method establishes the connection to the server. If the connection was
		* established, then this method will call getFrame() and return the EPP <greeting>
		* frame which is sent by the server upon connection. If connection fails, then
		* a PEAR_Error object explaining the error will be returned instead.
		* @param string the hostname
		* @param integer the TCP port
		* @param integer the timeout in seconds
		* @param boolean whether to connect using SSL
		* @return PEAR_Error|string a PEAR_Error on failure, or a string containing the server <greeting>
		*/
		function connect($host, $port=5544, $timeout=1, $ssl=true) {
			//$target = sprintf('%s://%s:%d', ($ssl === true ? 'ssl' : 'tcp'), $host, $port);
			$target = sprintf('%s://%s', ($ssl === true ? 'ssl' : 'tcp'), $host);
			if (!$this->socket = @fsockopen($target, $port,  $errno, $errstr, $timeout)) {
				return new PEAR_Error("Error connecting to $target: $errstr (code $errno)");
			} else {
				return $this->getFrame();

			}

		}

		/**
		* Get an EPP frame from the server.
		* This retrieves a frame from the server. Since the connection is blocking, this
		* method will wait until one becomes available. If the connection has been broken,
		* this method will return a PEAR_Error object, otherwise it will return a string
		* containing the XML from the server
		* @return PEAR_Error|string a PEAR_Error on failure, or a string containing the frame
		*/
		function getFrame() {
			if (feof($this->socket)) return new PEAR_Error("Connection appears to have closed.");

			$hdr = @fread($this->socket, 4);

			if (empty($hdr)) {
				return new PEAR_Error("Error reading from server: $php_errormsg");

			} else {
				$unpacked = unpack('N', $hdr);
				$answer = fread($this->socket, ($unpacked[1] - 4));

				return $answer;
			}
		}

		/**
		* Send an XML frame to the server.
		* This method sends an EPP frame to the server.
		* @param string the XML data to send
		* @return boolean the result of the fwrite() operation
		*/
		function sendFrame($xml) {
			return @fwrite($this->socket, pack('N', (strlen($xml)+4)).$xml);

		}

		/**
		* Close the connection.
		* This method closes the connection to the server. Note that the
		* EPP specification indicates that clients should send a <logout>
		* command before ending the session.
		* @return boolean the result of the fclose() operation
		*/
		function disconnect() {
			return @fclose($this->socket);
		}

	}

?>
