<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

class Request {

    public $xml;
    public $txnId;

    public static function make($action, $attrs = null, $children = null, $XmlExtension = null) {
        $xmlAction = XMLElement::make($action, $attrs, $children);

        return new self($xmlAction, $XmlExtension);
    }

    public function __construct($payload, $extension) {
        $this->txnId = uniqid();
        $this->xml = XMLElement::make('epp', [
            'xmlns' => 'urn:ietf:params:xml:ns:epp-1.0',
        ], [
            XMLElement::make('command', null, [
                $payload,
                $extension,
                XMLElement::make('clTRID', null, $this->txnId),
            ]),
        ]);
    }

    public function asXML() {
        return $this->xml->asXML();
    }

}
