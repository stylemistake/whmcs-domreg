<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

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
                $table
                    ->where('client_id', $clientId)
                    ->update([
                        'registrant_id' => $registrantId,
                    ]);
            }
            else {
                $table
                    ->where('client_id', $clientId)
                    ->delete();
            }
        }
        elseif ($registrantId) {
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
        $registrantsTable = self::getRegistrantsTable();
        $eppClient = self::getEpp();

        // Get registrant id from database
        $registrantId = $registrantsTable
            ->where('client_id', $clientId)
            ->value('registrant_id');

        // If not found
        if (!$registrantId) {
            // Get the domain
            $domain = $eppClient->getDomainOrNull($domainName);
            
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

                $eppClient->saveContact($registrant);

                // Get id from newly created registrant
                $registrantId = $registrant->id;
            }

            // Save registrant id to database
            $registrantsTable->insert([
                'client_id'     => $clientId,
                'registrant_id' => $registrantId,
            ]);

            // Return registrant object if we already have it
            if (isset($registrant)) {
                return $registrant;
            }
        }

        // Get registrant object from Domreg
        return $eppClient->getContact($registrantId);
    }

    /**
     * Retrieves an orgcode custom field from WHMCS
     */
    public static function getOrgCodeByUserId($userId) {
        static $fieldNames = [
            'Company ID',
            'Įmonės kodas',
        ];
        if (!$userId) {
            self::log('getOrgCodeByUserId', [$userId], null, [
                'warning' => 'User id was not specified.',
            ]);
            return null;
        }
        // Retrieve custom field id
        $row = Capsule::table('tblcustomfields')
            ->whereIn('fieldname', $fieldNames)
            ->first();
        if (!$row) {
            return null;
        }
        $fieldId = $row->id;
        // Retrieve custom field value
        $row = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', '=', $fieldId)
            ->where('relid', '=', $userId)
            ->first();
        if (!$row) {
            return null;
        }
        $orgCode = $row->value;
        // Return orgcode
        self::log('getOrgCodeByUserId', [$userId], [
            'fieldId' => $fieldId,
            'orgCode' => $orgCode,
        ]);
        return $orgCode;
    }

    public static function convertDomregStatusToWhmcsStatus($domregStatus)
    {
        switch ($domregStatus) {
            case 'inactive':
                # Domain has no nameservers set and therefore is not reachable
                $whmcsStatus = 'Pending';
                break;

            case 'serverHold':
                # Domain is inactive and has to be paid for to become active. (Late payment)
                $whmcsStatus = 'Redemption Period (Expired)';
                break;

            case 'pendingCreate':
                # Domain Registration request is sent but not yet processed
                $whmcsStatus = 'Pending Registration';
                break;

            case 'pendingTransfer':
                # Initiated transfer but not yet reviewed by the other registrar.
                $whmcsStatus = 'Pending Transfer';
                break;
            
            case 'registered':
                # Domain is Active
            case 'pendingRenew': 
                # Domain is Active, but has to be renewed before it expires.
            case 'clientAutoRenewProhibited':
                # Domain Active, but requested not to be renewed.
            case 'serverTransferProhibited':
                # Domain Active, but unavailable for transfer for the first and last months of the domain registration.
            case 'serverUpdateProhibited':
                # Domain is Active but locked for editing (because of payment or legal issues).
            case 'clientUpdateProhibited':
                # Domain is Active and locked for changes except for this status change.
            default:
                $whmcsStatus = 'Active';
        }

        return $whmcsStatus;
    }
}
