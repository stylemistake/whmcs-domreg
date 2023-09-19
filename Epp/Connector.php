<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

use SimpleXMLElement;

class Connector {

    private $socket;

    /**
     * Establishes a connect to the server
     *
     * @param string the hostname
     * @param integer the TCP port
     * @param integer the timeout in seconds
     * @param boolean whether to connect using SSL
     * @return self
     */
    public function connect($host, $port = 5544, $timeout = 10, $ssl = true) {
        $target = sprintf('%s://%s', ($ssl === true ? 'ssl' : 'tcp'), $host);
        $this->socket = @fsockopen($target, $port, $errno, $errstr, $timeout);
        if (!$this->socket) {
            throw new EppException("Error connecting to $target: $errstr (code $errno)");
        }
        // Validate connection
        $res = new Response($this->recvFrame());
        if (!$res->isGreeting()) {
            throw new EppException("Could not connect to Domreg (invalid EPP greeting)");
        }
        return $this;
    }

    /**
     * Receive an EPP frame from the server.
     *
     * @return string
     */
    public function recvFrame() {
        if (feof($this->socket)) {
            throw new EppException("Connection appears to have closed.");
        }
        $hdr = fread($this->socket, 4);
        $unpacked = unpack('N', $hdr);
        $frame = fread($this->socket, ($unpacked[1] - 4));
        // $this->debugPrint(null, $frame);
        return $frame;
    }

    /**
     * Sends an EPP request and waits for response.
     *
     * @param  Request $req
     * @return Response
     */
    public function send($req) {
        $this->sendFrame($req->asXML());
        $rpFrame = $this->recvFrame();
        // $this->debugPrint($req->asXML(), $rpFrame);
        return new Response($rpFrame, $req);
    }

    /**
     * Send an XML frame to the server.
     *
     * @param string the XML data to send
     * @return boolean the result of the fwrite() operation
     */
    public function sendFrame($frame) {
        if ($frame instanceof Request || $frame instanceof SimpleXMLElement) {
            $frame = $frame->asXML();
        }
        // $this->debugPrint($frame, null);
        return @fwrite($this->socket, pack('N', (strlen($frame)+4)) . $frame);
    }

    /**
     * Close the connection.
     *
     * @return boolean the result of the fclose() operation
     */
    public function disconnect() {
        return @fclose($this->socket);
    }

    private function debugPrint($rqmsg, $rpmsg) {
        static $stdout = null;
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            if (!$stdout) {
                $stdout = fopen('php://stdout', 'w');
            }
            fwrite($stdout, $rqmsg . "\n");
            fwrite($stdout, $rpmsg . "\n");
        }
        else {
            \Domreg\Store::log('Domreg_Epp_Connector', $rqmsg, $rpmsg);
        }
    }

}
