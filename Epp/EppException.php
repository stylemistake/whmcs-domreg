<?php

namespace Domreg\Epp;

use Exception;

class EppException extends Exception {

    /**
     * @var Request
     */
    public $req;

    /**
     * @var Response
     */
    public $res;

    public static function fromResponse(Response $res) {
        $e = new self($res->getMessage(), $res->getCode());
        $e->res = $res;
        $e->req = $res->req;
        return $e;
    }

}
