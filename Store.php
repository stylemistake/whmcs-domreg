<?php

namespace Domreg;

use WHMCS\Database\Capsule;

class Store {

    /**
     * Get an instance of Domreg EPP client
     *
     * @return Domreg\Epp\Domreg
     */
    public static function getEpp() {
        /**
         * @var Domreg\Epp\Domreg
         */
        static $epp = null;
        if ($epp !== null) {
            return $epp;
        }

        // Initialize Domreg EPP
        $params = getregistrarconfigoptions('domreg');
        $epp = new Epp\Domreg();
        if ($params['TestMode'] === 'enabled') {
            $epp->connectTest();
        }
        else {
            $epp->connect();
        }
        $epp->login($params['Username'], $params['Password']);

        // Define NsGroups
        $nsGroups = array_filter([
            $params['NsGroup1'],
            $params['NsGroup2'],
            $params['NsGroup3'],
            $params['NsGroup4'],
        ]);
        foreach ($nsGroups as $nsGroup) {
            $epp->defineNsGroup($nsGroup);
        }

        return $epp;
    }

    public static function log($action, $req = null, $res = null, $proc = null) {
        if ($req instanceof Epp\Entity) {
            $req = $req->toArray();
        }
        if ($res instanceof Epp\Entity) {
            $res = $res->toArray();
        }
        if ($proc instanceof Epp\Entity) {
            $proc = $proc->toArray();
        }
        logModuleCall('domreg', $action, $req, $res, $proc);
    }

    public static function getRegistrantsTable() {
        $params = getregistrarconfigoptions('domreg');
        $tableName = $params['RegistrantsTable'];
        if ($params['TestMode'] === 'enabled') {
            $tableName .= '_test';
        }
        $schema = Capsule::schema();
        // Create schema
        if (!$schema->hasTable($tableName)) {
            $schema->create($tableName, function ($table) {
                $table->integer('client_id')->primary();
                $table->string('registrant_id');
            });
        }
        // Return table object
        return Capsule::table($tableName);
    }

    public static function getClientRn($clientId) {
        return self::getRegistrantsTable()
            ->where('client_id', $clientId)
            ->value('registrant_id');
    }

    public static function setClientRn($clientId, $registrantId = null) {
        $table = Store::getRegistrantsTable();
        $row = $table
            ->where('client_id', $clientId)
            ->first();
        if ($row) {
            if ($registrantId) {
                $row->registrant_id = $registrantId;
                $row->save();
            }
            else {
                $row->delete();
            }
        }
        else {
            $table->insert([
                'client_id' => $clientId,
                'registrant_id' => $registrantId,
            ]);
        }
    }

    /**
     * Logically resolve correct registrant based off:
     *
     *  - Existence of RN within Domreg
     *  - RN associated with a WHMCS account
     *  - RN associated with a domain
     *
     * Creates new registrant in case it wasn't found
     * Always returns some registrant object.
     *
     * @param  string $clientId
     * @param  array  $params
     * @param  string $domainName
     * @return Domreg\Epp\Contact
     */
    public static function getRegistrant($clientId, $params = null, $domainName = null) {
        $table = self::getRegistrantsTable();
        $epp = self::getEpp();

        // Get registrant id from database
        $registrantId = $table
            ->where('client_id', $clientId)
            ->value('registrant_id');

        // If not found
        if (!$registrantId) {
            // Get the domain
            $domain = $epp->getDomainOrNull($domainName);
            // If domain exists
            if ($domain) {
                // Get id from domain
                $registrantId = $domain->registrant;
            }
            // If domain doesn't exist
            else {
                // Make a new registrant object
                $registrant = Epp\Contact::make()
                    ->fromWHMCSParams($params, 'registrant');
                $epp->saveContact($registrant);
                // Get id from newly created registrant
                $registrantId = $registrant->id;
            }
            // Save registrant id to database
            $table->insert([
                'client_id' => $clientId,
                'registrant_id' => $registrantId,
            ]);
            // Return registrant object if we already have it
            if (isset($registrant)) {
                return $registrant;
            }
        }

        // Get registrant object from Domreg
        return $epp->getContact($registrantId);
    }

}
