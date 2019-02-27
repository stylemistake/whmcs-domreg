<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

use WHMCS\Domains\DomainLookup\ResultsList;
use WHMCS\Domains\DomainLookup\SearchResult;
use WHMCS\Module\Registrar\Registrarmodule\ApiClient;
use WHMCS\Database\Capsule;
use Domreg\Epp;
use Domreg\Store;

define('WHMCS_MODULE', 'DOMREG');

require __DIR__ . '/vendor/autoload.php';

function domreg_MetaData() {
    return [
        'DisplayName' => 'Domreg (LT domains)',
        'APIVersion' => '2.0',
    ];
}

function domreg_GetConfigArray() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Domreg (LT domains)',
        ],
        'Username' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Domreg.lt RN login (required)',
        ],
        'Password' => [
            'Type' => 'password',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Domreg.lt RN password (required)',
        ],
        'TestMode' => [
            'Type' => 'dropdown',
            'Options' => [
                'enabled' => 'Enabled',
                'disabled' => 'Disabled',
            ],
            'Default' => 'enabled',
            'Description' => 'Testing mode',
        ],
        'RegistrantsTable' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => 'mod_domreg_registrants',
            'Description' => 'Default registrants table (automatically created)',
        ],
        'SupportContact' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Support contact id (eg. CN1234, required)',
        ],
        'NsGroup1' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Name of NsGroup 1 (optional)',
        ],
        'NsGroup2' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Name of NsGroup 2 (optional)',
        ],
        'NsGroup3' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Name of NsGroup 3 (optional)',
        ],
        'NsGroup4' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => '',
            'Description' => 'Name of NsGroup 4 (optional)',
        ],
        'AdminUser' => [
            'Type' => 'text',
            'Size' => '20',
            'Default' => 'admin',
            'Description' => 'WHMCS Admin User (for API calls)',
        ],
    ];
}

function domreg_AdminCustomButtonArray() {
    return [
        'Sync' => 'SyncManual',
    ];
}

function domreg_ClientAreaCustomButtonArray() {
    return [
        'Domreg RN' => 'RequestRn',
    ];
}

// TODO: Work in progress
function domreg_CheckAvailability($params) {
    $domainName = $params['tld']
        ? $params['sld'] . '.' . $params['tld']
        : $params['sld'] . '.lt';
    try {
        $epp = Store::getEpp();
        $domains = $epp->checkDomains([ $domainName ]);
        $results = new ResultsList();
        foreach ($domains as $i => $status) {
            $parts = explode('.', $domainName);
            $result = new SearchResult($parts[0], $parts[1]);
            $result->setStatus($status
                ? SearchResult::STATUS_NOT_REGISTERED
                : SearchResult::STATUS_REGISTERED);
            $results->append($result);
        }
        Store::log(__FUNCTION__, $params, $results);
        return $results;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RequestRn($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $res = [
            'success' => true,
            'templatefile' => 'requestrn',
            'vars' => [
                'registrant_id' => $domain->registrant,
            ],
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_GetNameservers($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        // NOTE: This one adds "1" into the output
        // $res = [
        //     'success' => true,
        // ];
        $res = [];
        foreach ($domain->ns as $i => $ns) {
            $res['ns' . ($i + 1)] = $ns->host;
        }
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_SaveNameservers($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $domain->clearNs();
        for ($i = 1; $i <= 4; $i++) {
            $domain->addNs($params['ns' . $i]);
        }
        $epp->updateDomain($domain);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RegisterDomain($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    $clientId = $params['userid'];
    $period = $params['regperiod'];
    try {
        $epp = Store::getEpp();
        $registrant = Store::getRegistrant($clientId, $params, $domainName);
        $domain = new Epp\Domain([
            'name' => $domainName,
            'period' => $period,
            'registrant' => $registrant->id,
            'contacts' => [
                $params['SupportContact'],
            ],
        ]);
        for ($i = 1; $i <= 4; $i++) {
            $domain->addNs($params['ns' . $i]);
        }
        $epp->createDomain($domain);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_TransferDomain($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    $transfersecret = $params['transfersecret'];
    $clientId = $params['userid'];
    try {
        $epp = Store::getEpp();
        $registrant = Store::getRegistrant($clientId, $params, $domainName);
        $domain = new Epp\Domain([
            'name' => $domainName,
            'registrant' => $registrant->id,
            'contacts' => [
                $params['SupportContact'],
            ],
        ]);
        for ($i = 1; $i <= 4; $i++) {
            $domain->addNs($params['ns' . $i]);
        }
        $epp->transferDomain($domain);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RenewDomain($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    $period = $params['regperiod'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        if ($domain->isQuarantined()) {
            $domain->period = $period;
            $epp->createDomain($domain);
            Store::log(__FUNCTION__, $params, [
                'created' => true,
                'quarantined' => true,
            ]);
        }
        else {
            $epp->renewDomain($domain, $period);
            Store::log(__FUNCTION__, $params, [
                'renewed' => true,
            ]);
        }
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RequestDelete($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $epp->deleteDomain($domainName);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_GetContactDetails($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $contact = $epp->getContact($domain->registrant);
        $res = [
            'Registrant' => [
                'Email' => $contact->email,
                'Phone Number' => $contact->voice,
                'Street' => $contact->street,
                'City' => $contact->city,
                'Region' => $contact->sp,
                'Post code' => $contact->pc,
                'Country code' => $contact->cc,
            ],
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_SaveContactDetails($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        // Get contact object
        $domain = $epp->getDomain($domainName);
        $contact = $epp->getContact($domain->registrant);
        // Update with new data
        $contact->fromWHMCSParams($params['contactdetails']['Registrant']);
        $epp->saveContact($contact);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RegisterNameserver($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $domain->addNs($params['nameserver'], $params['ipaddress']);
        $epp->updateDomain($domain);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_ModifyNameserver($params) {
    return domreg_RegisterNameserver($params);
}

function domreg_DeleteNameserver($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $domain->removeNs($params['nameserver']);
        $epp->updateDomain($domain);
        $res = [
            'success' => true,
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_TransferSync($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        $res = [
            'success' => true,
            'active' => $domain->isActive(),
            'registrationdate' => $domain->createdAt->format('Y-m-d'),
            'expirydate' => $domain->expiresAt->format('Y-m-d'),
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_Sync($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        // Try polling first
        // $epp->retrieveMessages(function (Epp\Message $msg) {
        //     Store::log('message', null, $msg);
        // });
        // Get domain info
        $domain = $epp->getDomain($domainName);
        $res = [
            'success' => true,
            'active' => $domain->isActive(),
            'registrationdate' => $domain->createdAt->format('Y-m-d'),
            'expirydate' => $domain->expiresAt->format('Y-m-d'),
        ];
        Store::log(__FUNCTION__, $params, $res);
        return $res;
    }
    // catch (Epp\EppException $e) {
    //     $e->req->getStatus()
    //     if ($domreg->executor->status['rcode'] == '2201') {
    //         localAPI('updateclientdomain', [
    //             'domainid' => $params['domainid'],
    //             'status' => 'Cancelled',
    //         ], $params['AdminUser']);
    //     }
    //     else {
    //         $error = $e->getMessage();
    //     }
    // }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_SyncManual($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainName);
        // Get grace days
        $graceDays = Capsule::table('tblconfiguration')
            ->where('setting', 'OrderDaysGrace')
            ->value('value');
        // Build request payload
        $req = [
            'domainid' => $params['domainid'],
            'regdate' => $domain->createdAt->format('Y-m-d'),
            'expirydate' => $domain->expiresAt->format('Y-m-d'),
            'nextduedate' => $domain->expiresAt
                ->sub(new DateInterval('P' . $graceDays . 'D'))
                ->format('Y-m-d'),
        ];
        if ($domain->isActive()) {
            $req['status'] = 'active';
        }
        // Send the request
        $res = localAPI('UpdateClientDomain', $req, $params['AdminUser']);
        if ($res['result'] === 'error') {
            Store::log(__FUNCTION__, $params, $res);
            return [
                'error' => $res['message'],
            ];
        }
        // Try redirecting
        if (isset($_SERVER['HTTP_REFERER'])) {
            http_response_code(301);
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
        Store::log(__FUNCTION__, $params);
        // Show a message with instructions
        return [
            'success' => true,
            'message' => '(Warning) You must refresh page to see the changes',
        ];
    }
    catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}
