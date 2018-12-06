<?php

namespace Domreg\Epp;

class Domain extends Entity {

    public $name;
    public $roid;
    public $period;
    public $onExpire;
    public $ns = [];

    /**
     * @var string
     */
    public $registrant;

    /**
     * List of contact ids
     * @var string[]
     */
    public $contacts = [];

    // Response fields
    public $status;

    /**
     * @var DateTimeImmutable
     */
    public $createdAt;

    /**
     * @var DateTimeImmutable
     */
    public $updatedAt;

    /**
     * @var DateTimeImmutable
     */
    public $expiresAt;

    private $pristine;

    public function __construct($data = []) {
        $this->fromArray($data);
    }

    /**
     * Start tracking changes.
     *
     * @return self
     */
    public function beginChanges() {
        $this->pristine = clone $this;
        return $this;
    }

    /**
     * Collect changes as an associative array.
     *
     * @return self
     */
    public function collectChanges() {
        $pristine = $this->pristine;
        $cmpNsike = function ($a, $b) {
            return $a->equals($b) ? 0 : -1;
        };
        $cmpObj = function ($a, $b) {
            return $a === $b ? 0 : -1;
        };
        return [
            'addNs' => array_udiff($this->ns, $pristine->ns, $cmpNsike),
            'remNs' => array_udiff($pristine->ns, $this->ns, $cmpNsike),
            'addContacts' => array_udiff($this->contacts, $pristine->contacts, $cmpObj),
            'remContacts' => array_udiff($pristine->contacts, $this->contacts, $cmpObj),
        ];
    }

    public function isActive() {
        return $this->status === 'registered';
    }

    public function isQuarantined() {
        return $this->status === 'pendingDelete';
    }

    /**
     * Add a nameserver with optional glue records.
     *
     * @param string $host
     * @param string $ipv4
     * @param string $ipv6
     * @return self
     */
    public function addNs($host, $ipv4 = null, $ipv6 = null) {
        // Do not add empty nameservers
        if (!$host) {
            return $this;
        }
        // Try to replace an existing Ns
        foreach ($this->ns as $i => $ns) {
            if ($ns->host === $host) {
                $this->ns[$i] = new Ns($host, $ipv4, $ipv6);
                return $this;
            }
        }
        // Add new Ns
        $this->ns[] = new Ns($host, $ipv4, $ipv6);
        return $this;
    }

    /**
     * Remove the nameserver.
     *
     * @param  string $host
     * @return self
     */
    public function removeNs($host) {
        foreach ($this->ns as $i => $ns) {
            if ($ns->host === $host) {
                unset($this->ns[$i]);
            }
        }
        return $this;
    }

    /**
     * Clear all nameservers.
     *
     * @return self
     */
    public function clearNs() {
        $this->ns = [];
        return $this;
    }

    /**
     * Add a contact.
     *
     * @param  Contact|string $contact
     * @return self
     */
    public function addContact($contact) {
        $this->contacts[] = $contact instanceof Contact
            ? $contact->id
            : $contact;
        return $this;
    }

    /**
     * Remove the contact.
     *
     * You can provide either a contact object (with id) or the contact id
     * as a string.
     *
     * @param  Contact|string $contact
     * @return self
     */
    public function removeContact($contact) {
        // Retrieve the contact id
        $id = $contact instanceof Contact
            ? $contact->id
            : $contact;
        if (!$id) {
            throw new Exception("Can't remove the contact without id!");
        }
        foreach ($this->contacts as $i => $contactId) {
            if ($contactId === $id) {
                unset($this->contacts[$i]);
            }
        }
        return $this;
    }

    /**
     * Shrink the domain name object before saving to Domreg EPP.
     *
     * @param  NsGroup[] $nsGroups
     * @return self
     */
    public function shrink($nsGroups) {
        foreach ($nsGroups as $nsGroup) {
            $this->ns = $nsGroup->shrinkNsArray($this->ns);
        }
        return $this;
    }

    /**
     * Expand the domain name object after retrieval from Domreg EPP.
     *
     * @return self
     */
    public function expand() {
        $result = [];
        foreach ($this->ns as $ns) {
            if ($ns instanceof NsGroup) {
                $result = array_merge($result, $ns->toNsArray());
                continue;
            }
            $result[] = $ns;
        }
        $this->ns = $result;
        return $this;
    }

}
