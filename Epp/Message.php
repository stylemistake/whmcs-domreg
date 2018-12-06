<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

use DateTimeImmutable;

class Message extends Entity {

    public $id;
    public $count;

    /**
     * @var DateTimeImmutable
     */
    public $queuedAt;
    public $resCode;

    public $obType;
    public $object;
    public $notice;

    public function fromResponse(Response $res) {
        $this->id = xml_query_as_int($res->xml, '//epp:msgQ/@id');
        $this->count = xml_query_as_int($res->xml, '//epp:msgQ/@count');
        $this->queuedAt = xml_query_as_datetime($res->xml, '//epp:msgQ/qDate');
        $this->obType = xml_query($res->xml, '//event:obType');
        $this->object = xml_query($res->xml, '//event:object');
        $this->notice = xml_query($res->xml, '//event:notice');
        $this->resCode = $res->getCode();
        return $this;
    }

    public function needsAck() {
        return $this->resCode === 1301;
    }

    public function isQueueEmpty() {
        return $this->resCode === 1300;
    }

}
