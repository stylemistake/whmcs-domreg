<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

namespace Domreg\Epp;

class NsGroup extends Entity implements NsLike {

    public $name;
    public $nameservers;

    public function __construct($name, $nameservers = []) {
        $this->name = $name;
        $this->nameservers = $nameservers;
    }

    /**
     * Check if group contains a host.
     *
     * @param  string $host
     * @return bool
     */
    public function contains($host) {
        return in_array($host, $this->nameservers);
    }

    public function equals($obj) {
        if (!($obj instanceof self)) {
            return false;
        }
        return $this->name === $obj->name;
    }

    /**
     * Converts NsGroup to an array of Ns
     *
     * @return Ns[]
     */
    public function toNsArray() {
        $result = [];
        foreach ($this->nameservers as $host) {
            $result[] = new Ns($host);
        }
        return $result;
    }

    /**
     * Check, if given Ns array can be shrunk by this NsGroup.
     * @param  array $nsArray
     * @return array
     */
    public function canShrinkNsArray($nsArray) {
        foreach ($this->nameservers as $host) {
            // Check if given $nsArray contains a host from this group.
            $containsNs = false;
            foreach ($nsArray as $ns) {
                // Ignore everything that is not a nameserver
                if (!($ns instanceof Ns)) {
                    continue;
                }
                if ($ns->host === $host) {
                    $containsNs = true;
                    break;
                }
            }
            if (!$containsNs) {
                // Stop at this point, because given $nsArray doesn't contain
                // a nameserver in this group.
                return false;
            }
        }
        return true;
    }

    /**
     * Shrinks an array of Ns by replacing matching Ns with its own NsGroup.
     *
     * @param  array $nsArray
     * @return array
     */
    public function shrinkNsArray(array $nsArray) {
        // Determine if we can shrink
        if (!$this->canShrinkNsArray($nsArray)) {
            return $nsArray;
        }
        // If previous loop passes, we have a shrinkable array!
        $shrunkNsArray = [];
        // Add ourselves to the array.
        $shrunkNsArray[] = $this;
        // Add all nameservers except those that are in group.
        foreach ($nsArray as $ns) {
            // Ignore nameservers in this group
            if ($ns instanceof Ns && $this->contains($ns->host)) {
                continue;
            }
            // Add everything else
            $shrunkNsArray[] = $ns;
        }
        return $shrunkNsArray;
    }

    public function toXMLElement() {
        return XMLElement::make('domain:hostGroup', null, $this->name);
    }

}
