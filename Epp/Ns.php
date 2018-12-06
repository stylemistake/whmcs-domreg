<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

class Ns extends Entity implements NsLike {

    public $host;
    public $ipv4;
    public $ipv6;

    public function __construct($host, $ipv4 = null, $ipv6 = null) {
        $this->host = $host;
        $this->ipv4 = $ipv4;
        $this->ipv6 = $ipv6;
    }

    public function equals($obj) {
        if (!($obj instanceof self)) {
            return false;
        }
        return $this->host === $obj->host
            && $this->ipv4 === $obj->ipv4
            && $this->ipv6 === $obj->ipv6;
    }

    public function hasGlue() {
        return $this->ipv4 !== null
            || $this->ipv6 !== null;
    }

    public function toXMLElement() {
        return XMLElement::make('domain:hostAttr', null, [
            XMLElement::make('domain:hostName', null, $this->host),
            XMLElement::optional('domain:hostAddr', [ 'ip' => 'v4' ], $this->ipv4),
            XMLElement::optional('domain:hostAddr', [ 'ip' => 'v6' ], $this->ipv6),
        ]);
    }

}
