<?php

namespace Domreg\Epp;

class Domreg {

    const XMLNS_EPP = 'urn:ietf:params:xml:ns:epp-1.0';
    const XMLNS_SECDNS = 'urn:ietf:params:xml:ns:secDNS-1.1';
    const XMLNS_DOMAIN = 'http://www.domreg.lt/epp/xml/domreg-domain-1.0';
    const XMLNS_CONTACT = 'http://www.domreg.lt/epp/xml/domreg-contact-1.0';
    const XMLNS_NSGROUP = 'http://www.domreg.lt/epp/xml/domreg-nsgroup-1.0';
    const XMLNS_EVENT = 'http://www.domreg.lt/epp/xml/domreg-event-1.0';
    const XMLNS_PERMIT = 'http://www.domreg.lt/epp/xml/domreg-permit-1.0';

    private $epp;
    private $loggedIn = false;

    // NsGroup cache
    private $nsGroups = [];

    public function __destruct() {
        $this->logout()->disconnect();
    }

    /**
     * Connect to the Domreg EPP.
     *
     * @return self
     */
    public function connect() {
        $this->epp = new Connector();
        $this->epp->connect('epp.domreg.lt', 700);
        return $this;
    }

    /**
     * Connect to the Testnet of Domreg EPP.
     *
     * @return self
     */
    public function connectTest() {
        $this->epp = new Connector();
        $this->epp->connect('epp.test.domreg.lt', 700);
        return $this;
    }

    /**
     * Disconnect from EPP.
     *
     * @return self
     */
    public function disconnect() {
        if ($this->epp) {
            $this->epp->disconnect();
        }
        $this->epp = null;
        return $this;
    }

    /**
     * Log into the Domreg EPP.
     *
     * @return self
     */
    public function login($username, $password) {
        $req = Request::make('login', null, [
            XMLElement::make('clID', null, $username),
            XMLElement::make('pw', null, $password),
            XMLElement::make('options', null, [
                XMLElement::make('version', null, '1.0'),
                XMLElement::make('lang', null, 'en'),
            ]),
            XMLElement::make('svcs', null, [
                XMLElement::make('objURI', null, self::XMLNS_EPP),
                XMLElement::make('objURI', null, self::XMLNS_SECDNS),
                XMLElement::make('objURI', null, self::XMLNS_DOMAIN),
                XMLElement::make('objURI', null, self::XMLNS_CONTACT),
                XMLElement::make('objURI', null, self::XMLNS_NSGROUP),
                XMLElement::make('objURI', null, self::XMLNS_EVENT),
                XMLElement::make('objURI', null, self::XMLNS_PERMIT),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $this->loggedIn = true;
        return $this;
    }

    /**
     * Log out of the Domreg EPP.
     *
     * @return self
     */
    public function logout() {
        if ($this->loggedIn) {
            $req = Request::make('logout');
            $res = $this->epp->send($req)->throwIfError();
        }
        $this->loggedIn = false;
        return $this;
    }

    /**
     * Get a Contact by name
     *
     * @param  string $id
     * @return Contact
     */
    public function getContact($id) {
        $contact = new Contact([ 'id' => $id ]);
        $req = Request::make('info', null, $contact->toXMLElement('info'));
        $res = $this->epp->send($req)->throwIfError();
        $contact->fromResponse($res);
        return $contact;
    }

    /**
     * Save the contact object.
     *
     * @param  Contact $entity
     * @return Contact
     */
    public function saveContact(Contact $contact) {
        $req = $contact->id
            ? Request::make('update', null, $contact->toXMLElement('update'))
            : Request::make('create', null, $contact->toXMLElement('create'));
        $res = $this->epp->send($req)->throwIfError();
        return $contact->fromResponse($res);
    }

    /**
     * Delete the contact object.
     *
     * You can pass either a Contact object, or a contact id string.
     *
     * @param  string $id
     * @return self
     */
    public function deleteContact($contact) {
        $id = $contact instanceof Contact
            ? $contact->id
            : $contact;
        $contact = new Contact([ 'id' => $id ]);
        $req = Request::make('delete', null, $contact->toXMLElement('delete'));
        $res = $this->epp->send($req)->throwIfError();
        return $this;
    }

    /**
     * Check nameserver groups and returns a result in form of [name => bool].
     *
     * @param  string[] $names
     * @return bool[]
     */
    public function checkNsGroups(array $names) {
        $req = Request::make('check', null, [
            XMLElement::make('nsgroup:check', [
                'xmlns:nsgroup' => self::XMLNS_NSGROUP,
            ], [
                array_map(function ($name) {
                    return XMLElement::make('nsgroup:name', null, $name);
                }, $names),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $elements = $res->getData()->xpath('//*[@avail]');
        $result = [];
        foreach ($elements as $element) {
            $result[(string) $element] = (bool) (string) $element['avail'];
        }
        return $result;
    }

    /**
     * Define an NsGroup name.
     *
     * Methods such as 'getAllNsGroups()' don't know what they can retrieve.
     * We need to explicitly define NsGroups, so they could retrieve them by
     * default.
     *
     * @param  string $name
     * @return self
     */
    public function defineNsGroup($name) {
        $this->nsGroups[$name] = null;
    }

    /**
     * Get an NsGroup by name.
     *
     * @param  string $name
     * @return NsGroup
     */
    public function getNsGroup($name) {
        if (isset($this->nsGroups[$name])) {
            return $this->nsGroups[$name];
        }
        $req = Request::make('info', null, [
            XMLElement::make('nsgroup:info', [
                'xmlns:nsgroup' => self::XMLNS_NSGROUP,
            ], [
                XMLElement::make('nsgroup:name', null, $name),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $xml = $res->getData();
        $this->nsGroups[$name] = new NsGroup($name,
            xml_query_all($xml, '//nsgroup:ns'));
        return $this->nsGroups[$name];
    }

    /**
     * Get all NsGroups (defined or previously queried).
     *
     * @return NsGroup[]
     */
    public function getAllNsGroups() {
        $names = array_keys($this->nsGroups);
        $result = [];
        foreach ($names as $name) {
            $result[] = $this->getNsGroup($name);
        }
        return $result;
    }

    /**
     * Save an NsGroup.
     *
     * @param  NsGroup $entity
     * @return NsGroup
     */
    public function saveNsGroup(NsGroup $nsGroup) {
        $name = $nsGroup->name;
        // Check for name
        $check = $this->checkNsGroups([ $name ]);
        $available = $check[$name];
        // Store
        $action = $available
            ? 'create'
            : 'update';
        $req = Request::make($action, null, [
            XMLElement::make('nsgroup:' . $action, [
                'xmlns:nsgroup' => self::XMLNS_NSGROUP,
            ], [
                XMLElement::make('name', null, $name),
                array_map(function ($x) {
                    return XMLElement::make('ns', null, $x);
                }, $nsGroup->nameservers),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        return $nsGroup;
    }

    /**
     * Delete an NsGroup by name.
     *
     * @param  string $name
     * @return self
     */
    public function deleteNsGroup($name) {
        $req = Request::make('delete', null, [
            XMLElement::make('nsgroup:delete', [
                'xmlns:nsgroup' => self::XMLNS_NSGROUP,
            ], [
                XMLElement::make('name', null, $name),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        return $this;
    }

    /**
     * Check if domain name is available.
     *
     * @param  string $name
     * @return bool
     */
    public function checkDomain($name) {
        $result = $this->checkDomains([ $name ]);
        return $result[$name];
    }

    /**
     * Check if multiple domain names are available.
     *
     * @param  string[] $name
     * @return bool[]
     */
    public function checkDomains($names) {
        $req = Request::make('check', null, [
            XMLElement::make('domain:check', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                array_map(function ($name) {
                    return XMLElement::make('domain:name', null, $name);
                }, $names),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $elements = $res->getData()->xpath('//*[@avail]');
        $result = [];
        foreach ($elements as $element) {
            $result[(string) $element] = (bool) (string) $element['avail'];
        }
        return $result;
    }

    /**
     * Retrieve the domain object.
     *
     * @param  string $name
     * @return Domain
     */
    public function getDomain($name) {
        $req = Request::make('info', null, [
            XMLElement::make('domain:info', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $name),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $xml = $res->getData();

        $domain = new Domain();
        $domain->name = xml_query($xml, '//domain:name');
        $domain->roid = xml_query($xml, '//domain:roid');
        $domain->onExpire = xml_query($xml, '//domain:onExpire');
        $domain->status = xml_query($xml, '//domain:status/@s');
        $domain->registrant = xml_query($xml, '//domain:registrant');
        $domain->contacts = xml_query_all($xml, '//domain:contact');
        $domain->createdAt = xml_query_as_datetime($xml, '//domain:crDate');
        $domain->updatedAt = xml_query_as_datetime($xml, '//domain:upDate');
        $domain->expiresAt = xml_query_as_datetime($xml, '//domain:exDate');

        $nsGroupNames = xml_query_all($xml, '//domain:hostGroup');
        foreach ($nsGroupNames as $nsGroupName) {
            $domain->ns[] = $this->getNsGroup($nsGroupName);
        }

        foreach ($xml->xpath('//domain:hostAttr') as $hostAttr) {
            $domain->ns[] = new Ns(
                xml_query($hostAttr, './domain:hostName'),
                xml_query($hostAttr, './domain:hostAddr[@ip=v4]'),
                xml_query($hostAttr, './domain:hostAddr[@ip=v6]'));
        }

        return $domain
            ->beginChanges()
            ->expand();
    }

    /**
     * Same as getDomain, but returns null instead of throwing an exception.
     *
     * @param  string $name
     * @return Domain
     */
    public function getDomainOrNull($name = null) {
        if (!$name) {
            return null;
        }
        try {
            return $this->getDomain($name);
        }
        catch (EppException $e) {
            return null;
        }
    }

    /**
     * Create a domain name.
     *
     * @param  Domain $domain
     * @return Domain
     */
    public function createDomain(Domain $domain) {
        // Shrink nameserver array with available NsGroups
        $domain->shrink($this->getAllNsGroups());
        // Make a request
        $req = Request::make('create', null, [
            XMLElement::make('domain:create', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $domain->name),
                XMLElement::optional('domain:period', [ 'unit' => 'y' ], $domain->period),
                XMLElement::optional('domain:onExpire', null, $domain->onExpire),
                XMLElement::optional('domain:ns', null, array_map(function ($entity) {
                    return $entity->toXMLElement();
                }, $domain->ns)),
                XMLElement::make('domain:registrant', null, $domain->registrant),
                array_map(function ($contact) {
                    return XMLElement::make('domain:contact', null, $contact);
                }, $domain->contacts),
            ]),
        ]);
        // Send a request
        $res = $this->epp->send($req)->throwIfError();
        $xml = $res->getData();
        $domain->name = xml_query($xml, '//domain:name');
        $domain->createdAt = xml_query_as_datetime($xml, '//domain:crDate');
        $domain->expiresAt = xml_query_as_datetime($xml, '//domain:exDate');
        return $domain
            ->beginChanges()
            ->expand();
    }

    /**
     * Update the domain.
     *
     * Currently only supports updates to nameservers and onExpire field.
     *
     * @param  Domain $domain
     * @return self
     */
    public function updateDomain(Domain $domain) {
        $changes = $domain
            ->shrink($this->getAllNsGroups())
            ->collectChanges();
        // Make a request
        $req = Request::make('update', null, [
            XMLElement::make('domain:update', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $domain->name),
                XMLElement::optional('domain:add', null, [
                    XMLElement::optional('domain:ns', null, array_map(function ($entity) {
                        return $entity->toXMLElement();
                    }, $changes['addNs'])),
                    array_map(function ($contact) {
                        return XMLElement::make('domain:contact', null, $contact);
                    }, $changes['addContacts']),
                ]),
                XMLElement::optional('domain:rem', null, [
                    XMLElement::optional('domain:ns', null, array_map(function ($entity) {
                        return $entity->toXMLElement();
                    }, $changes['remNs'])),
                    array_map(function ($contact) {
                        return XMLElement::make('domain:contact', null, $contact);
                    }, $changes['remContacts']),
                ]),
                XMLElement::optional('domain:chg', null, [
                    XMLElement::optional('domain:onExpire', null, $domain->onExpire),
                ]),
            ]),
        ]);
        // Send a request
        $res = $this->epp->send($req)->throwIfError();
        $xml = $res->getData();
        $domain->createdAt = xml_query_as_datetime($xml, '//domain:crDate');
        $domain->expiresAt = xml_query_as_datetime($xml, '//domain:exDate');
        $domain->beginChanges()->expand();
        return $this;
    }

    /**
     * Delete the domain.
     *
     * You can pass either a Domain object, or a domain name string.
     *
     * @param  Domain|string $domain
     * @return self
     */
    public function deleteDomain($domain) {
        $name = $domain instanceof Domain
            ? $domain->name
            : $domain;
        $req = Request::make('delete', null, [
            XMLElement::make('domain:delete', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $name),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        return $this;
    }

    /**
     * Complete the domain transfer.
     *
     * @param  Domain $domain
     * @param  string $trType
     * @return self
     */
    public function transferDomain(Domain $domain, $trType = 'transfer') {
        $req = Request::make('transfer', [
            'op' => 'request',
        ], [
            XMLElement::make('domain:transfer', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $domain->name),
                XMLElement::make('domain:trType', null, $trType),
                XMLElement::optional('domain:onExpire', null, $domain->onExpire),
                XMLElement::optional('domain:ns', null, array_map(function ($entity) {
                    return $entity->toXMLElement();
                }, $domain->ns)),
                XMLElement::make('domain:registrant', null, $domain->registrant),
                array_map(function ($contact) {
                    return XMLElement::make('domain:contact', null, $contact);
                }, $domain->contacts),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        return $this;
    }

    /**
     * Complete the domain trade.
     *
     * @param  Domain $domain
     * @return self
     */
    public function tradeDomain(Domain $domain) {
        return $this->transferDomain($domain, 'trade');
    }

    /**
     * Renew the domain for a specified period.
     *
     * @param  Domain $domain
     * @param  int $period Renew for N years
     * @return self
     */
    public function renewDomain(Domain $domain, $period = null) {
        if ($period) {
            $domain->period = $period;
        }
        $req = Request::make('renew', null, [
            XMLElement::make('domain:renew', [
                'xmlns:domain' => self::XMLNS_DOMAIN,
            ], [
                XMLElement::make('domain:name', null, $domain->name),
                XMLElement::make('domain:curExpDate', null, $domain->expiresAt->format('Y-m-d')),
                XMLElement::make('domain:period', [ 'unit' => 'y' ], $domain->period),
            ]),
        ]);
        $res = $this->epp->send($req)->throwIfError();
        $xml = $res->getData();
        $domain->expiresAt = xml_query_as_datetime($xml, '//domain:exDate');
        return $this;
    }

    /**
     * Low-level method for message polling.
     *
     * @param  string $op
     * @param  int $msgId
     * @return Message
     */
    public function poll($op = 'req', $msgId = null) {
        $req = Request::make('poll', [
            'op' => $op,
            'msgID' => $msgId,
        ]);
        $res = $this->epp->send($req)->throwIfError();
        return Message::make()->fromResponse($res);
    }

    /**
     * Retrieves and handles all messages from Domreg in a user-defined
     * callback function.
     *
     * Return false inside the callback to break the loop prematurely;
     * It will also keep the current message in the queue.
     *
     * @param  callable $fn $fn(Epp\Message $msg) -> bool
     * @return self
     */
    public function retrieveMessages(callable $fn) {
        while (true) {
            $msg = $this->poll();
            // Quit when queue is empty
            if ($msg->isQueueEmpty()) {
                return $this;
            }
            // Handle the message
            $result = $fn($msg);
            // Send Ack
            if ($msg->needsAck()) {
                $msg = $this->poll('ack', $msg->id);
                // Quit when queue is empty
                if ($msg->isQueueEmpty()) {
                    return $this;
                }
            }
        }
        return $this;
    }

}
