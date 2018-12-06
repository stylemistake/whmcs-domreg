<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

use SimpleXMLElement;

class Response {

    public $xml;
    public $req;

    public function __construct($frame, Request $req = null) {
        $this->xml = new SimpleXMLElement($frame);
        $this->xml->registerXPathNamespace('epp', 'urn:ietf:params:xml:ns:epp-1.0');
        $this->xml->registerXPathNamespace('secDNS', 'urn:ietf:params:xml:ns:secDNS-1.1');
        $this->xml->registerXPathNamespace('domain', 'http://www.domreg.lt/epp/xml/domreg-domain-1.0');
        $this->xml->registerXPathNamespace('contact', 'http://www.domreg.lt/epp/xml/domreg-contact-1.0');
        $this->xml->registerXPathNamespace('nsgroup', 'http://www.domreg.lt/epp/xml/domreg-nsgroup-1.0');
        $this->xml->registerXPathNamespace('event', 'http://www.domreg.lt/epp/xml/domreg-event-1.0');
        $this->xml->registerXPathNamespace('permit', 'http://www.domreg.lt/epp/xml/domreg-permit-1.0');
        $this->req = $req;
    }

    public function matchesRequest($req) {
        $txnId = (string) $this->xml->xpath('//epp:trID/epp:clTRID')[0];
        return $req->txnId === $txnId;
    }

    public function throwIfError() {
        if (!$this->isOk()) {
            throw new EppException($this->getMessage(), $this->getCode());
        }
        if (!$this->matchesRequest($this->req)) {
            throw new EppException("EPP response transaction id doesn't match!");
        }
        return $this;
    }

    public function getCode() {
        return (int) $this->xml
            ->xpath('//epp:response/epp:result')[0]
            ->attributes()
            ->code;
    }

    public function getMessage() {
        return (string) $this->xml
            ->xpath('//epp:response/epp:result/epp:msg')[0];
    }

    public function getData() {
        $xml = $this->xml->xpath('//epp:response/epp:resData/*');
        return reset($xml);
    }

    public function isGreeting() {
        return !!$this->xml->xpath('//epp:greeting');
    }

    public function isOk() {
        $code = $this->getCode();
        return $code >= 1000 && $code < 2000;
    }

    public function asXML() {
        return $this->xml->asXML();
    }

}
