<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

use Domreg\Store;

class Contact extends Entity {

    public $id;

    // Postal info
    public $name;
    public $org;
    public $orgcode;

    // Postal info -> Address
    public $street;
    public $city;
    public $sp;
    public $pc;
    public $cc;

    // Other fields
    public $voice;
    public $fax;
    public $email;
    public $role;

    // Response fields
    public $createdAt;
    public $updatedAt;

    public function __construct($data = []) {
        $this->fromArray($data);
    }

    public function __toString() {
        return $this->id;
    }

    public function fromResponse(Response $res) {
        static $mapping = [
            'id' => '//contact:id',
            'name' => '//contact:name',
            'org' => '//contact:org',
            'orgcode' => '//contact:orgcode',
            'street' => '//contact:street',
            'city' => '//contact:city',
            'sp' => '//contact:sp',
            'pc' => '//contact:pc',
            'cc' => '//contact:cc',
            'voice' => '//contact:voice',
            'fax' => '//contact:fax',
            'email' => '//contact:email',
            'role' => '//contact:role',
            'crDate' => '//contact:crDate',
            'upDate' => '//contact:upDate',
        ];
        $xml = $res->getData();
        if ($xml === false) {
            return $this;
        }
        foreach ($mapping as $prop => $path) {
            $value = xml_query($xml, $path);
            if (isset($value)) {
                $this->{$prop} = $value;
            }
        }
        return $this;
    }

    /**
     * WHMCS related
     *
     * @param  array $params
     * @param  string $role
     * @return self
     */
    public function fromWHMCSParams($params, $role = null) {
        // Set role
        if ($role) {
            $this->role = $role;
        }
        // Default fields (when creating a contact)
        if (!empty($params['firstname']) or !empty($params['lastname'])) {
            $this->name = trim($params['firstname'] . ' ' . $params['lastname']);
        }
        if (!empty($params['address1']) or !empty($params['address2'])) {
            $this->street = trim($params['address1'] . ' ' . $params['address2']);
        }
        if (!empty($params['city'])) {
            $this->city = trim($params['city']);
        }
        if (!empty($params['country'])) {
            $this->cc = strtolower($params['country']);
        }
        if (!empty($params['phonenumber'])) {
            $this->voice = '+' . $params['phonecc'] . '.' . $params['phonenumber'];
        }
        if (!empty($params['email'])) {
            $this->email = strtolower($params['email']);
        }
        if (!empty($params['state'])) {
            $this->sp = trim($params['state']);
        }
        if (!empty($params['postcode'])) {
            $this->pc = trim($params['postcode']);
        }
        if (!empty($params['companyname'])) {
            $this->org = trim($params['companyname']);
            // Orgcode (custom field)
            $this->orgcode = Store::getOrgCodeByUserId($params['userid']);
        }
        // Additional fields (when editing contact)
        if (!empty($params['Email'])) {
            $this->email = $params['Email'];
        }
        if (!empty($params['Phone Number'])) {
            $this->voice = $params['Phone Number'];
        }
        if (!empty($params['Street'])) {
            $this->street = trim($params['Street']);
        }
        if (!empty($params['City'])) {
            $this->city = trim($params['City']);
        }
        if (!empty($params['Region'])) {
            $this->sp = trim($params['Region']);
        }
        if (!empty($params['Post code'])) {
            $this->pc = trim($params['Post code']);
        }
        if (!empty($params['ZIP'])) {
            $this->pc = trim($params['ZIP']);
        }
        if (!empty($params['Country code'])) {
            $this->cc = trim($params['Country code']);
        }
        return $this;
    }

    private function wrapUpdate($action, $value) {
        // Return unchanged value
        if ($action !== 'update') {
            return $value;
        }
        // Wrap into <chg> element
        return XMLElement::make('contact:chg', null, $value);
    }

    private function wrapCreate($action, $value) {
        if ($action !== 'create') {
            return null;
        }
        return $value;
    }

    public function toXMLElement($action) {
        return XMLElement::make('contact:' . $action, [
            'xmlns:contact' => 'http://www.domreg.lt/epp/xml/domreg-contact-1.1',
        ], [
            XMLElement::optional('contact:id', null, $this->id),
            $this->wrapUpdate($action, [
                XMLElement::optional('contact:postalInfo', [ 'type' => 'loc' ], [
                    XMLElement::optional('contact:name', null, $this->name),
                    XMLElement::optional('contact:org', null, $this->org),
                    XMLElement::optional('contact:addr', null, [
                        XMLElement::optional('contact:street', null, $this->street),
                        XMLElement::optional('contact:city', null, $this->city),
                        XMLElement::optional('contact:sp', null, $this->sp),
                        XMLElement::optional('contact:pc', null, $this->pc),
                        XMLElement::optional('contact:cc', null, $this->cc),
                    ]),
                ]),
                XMLElement::optional('contact:voice', null, $this->voice),
                XMLElement::optional('contact:email', null, $this->email),
                XMLElement::optional('contact:orgcode', null, $this->orgcode),
            ]),
            $this->wrapCreate($action, [
                XMLElement::optional('contact:role', null, $this->role),
            ]),
        ]);
    }

}
