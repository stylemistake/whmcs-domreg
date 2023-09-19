<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

// use WHMCS\Domains\DomainLookup\ResultsList;
// use WHMCS\Domains\DomainLookup\SearchResult;
// use WHMCS\Module\Registrar\Registrarmodule\ApiClient;
use WHMCS\Database\Capsule;
use Domreg\Epp;
use Domreg\Store;

define('WHMCS_MODULE', 'DOMREG');

require __DIR__ . '/vendor/autoload.php';

function domreg_MetaData() {
    return [
        'DisplayName' => 'Domreg (LT domains)',
        'APIVersion' => '1.1',
        'Version' => '2.0'
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
        'Cancel EPP Code' => 'CancelEPPCode'
    ];
}

function domreg_ClientAreaCustomButtonArray() {
    global $_LANG;

    return [
        $_LANG['domaincanceleppcode'] => 'ClientAreaCancelEPPCode',
        $_LANG['domaindomreggetrn'] => 'RequestRn',
    ];
}

// TODO: Work in progress
// function domreg_CheckAvailability($params) {
//     $domainName = $params['tld']
//         ? $params['sld'] . '.' . $params['tld']
//         : $params['sld'] . '.lt';
//     try {
//         $epp = Store::getEpp();
//         $domains = $epp->checkDomains([ $domainName ]);
//         $results = new ResultsList();
//         foreach ($domains as $i => $status) {
//             $parts = explode('.', $domainName);
//             $result = new SearchResult($parts[0], $parts[1]);
//             $result->setStatus($status
//                 ? SearchResult::STATUS_NOT_REGISTERED
//                 : SearchResult::STATUS_REGISTERED);
//             $results->append($result);
//         }
//         Store::log(__FUNCTION__, $params, $results);
//         return $results;
//     }
//     catch (Exception $e) {
//         Store::log(__FUNCTION__, $params, null, $e);
//         return [
//             'error' => $e->getMessage(),
//         ];
//     }
// }

function domreg_RequestRn($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);
        $response = [
            'success' => true,
            'templatefile' => 'requestrn',
            'vars' => [
                'registrant_id' => $domain->registrant,
            ],
        ];
        Store::log(__FUNCTION__, $domainName, $response);

        return $response;
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_GetNameservers($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);

        $response = [];
        foreach ($domain->ns as $i => $ns) {
            $response['ns' . ($i + 1)] = $ns->host;
        }

        Store::log(__FUNCTION__, $domainName, ['nameservers' => $response, 'domain' => $domain]);

        return $response;
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_SaveNameservers($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);
        $domain->clearNs();

        for ($i = 1; $i <= 5; $i++) {
            $domain->addNs($params['ns' . $i]);
        }

        $response = $eppClient->updateDomain($domain);

        Store::log(__FUNCTION__, $domain, $response);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
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
        $eppClient = Store::getEpp();
        $registrant = Store::getRegistrant($clientId, $params, $domainName);
        $domain = new Epp\Domain([
            'name' => $domainName,
            'period' => $period,
            'onExpire' => 'delete',
            'registrant' => $registrant->id,
            'contacts' => [
                $params['SupportContact'],
            ],
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $domain->addNs($params['ns' . $i]);
        }

        $response = $eppClient->createDomain($domain);

        Store::log(__FUNCTION__, $domain, $response);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_TransferDomain($params) {
    $domainName = $params['domain'];
    $eppCode    = $params['eppcode'];
    $clientId   = $params['userid'];

    try {
        $eppClient = Store::getEpp();
        $registrant = Store::getRegistrant($clientId, $params, $domainName);

        $domain = new Epp\Domain([
            'name' => $domainName,
            'registrant' => $registrant->id,
            'contacts' => [
                $params['SupportContact'],
            ],
            'onExpire' => 'delete',
            'eppCode' => $eppCode
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $domain->addNs($params['ns' . $i]);
        }

        $response = $eppClient->transferDomain($domain);

        Store::log(__FUNCTION__, $domain, $response);

        return [
            'success' => true
        ];
    } catch (Exception $e) {
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
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);

        if ($domain->isQuarantined()) {
            $domain->period = $period;
            $eppClient->createDomain($domain);

            Store::log(__FUNCTION__, $params, [
                'created' => true,
                'quarantined' => true,
            ]);
        } else {
            $response = $eppClient->renewDomain($domain, $period);

            Store::log(__FUNCTION__, $params, $response);
        }

        return [
            'success' => true
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RequestDelete($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $response = $eppClient->deleteDomain($domainName);

        Store::log(__FUNCTION__, $domainName, $response);
        
        return [
            'success' => true
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);
        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_GetContactDetails($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);
        $contact = $eppClient->getContact($domain->registrant);

        $response = [
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

        Store::log(__FUNCTION__, $domainName, $response);

        return $response;
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_SaveContactDetails($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        
        // Get contact object
        $domain = $eppClient->getDomain($domainName);
        $contact = $eppClient->getContact($domain->registrant);

        // Update with new data
        $contact->fromWHMCSParams($params['contactdetails']['Registrant']);
        
        $response = $eppClient->saveContact($contact);
        Store::log(__FUNCTION__, $contact, $response);

        return [
            'success' => true,
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_RegisterNameserver($params) {
    $domainName = $params['sld'] . '.' . $params['tld'];
    try {
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);
        $domain->addNs($params['nameserver'], $params['ipaddress']);

        $response = $eppClient->updateDomain($domain);
        Store::log(__FUNCTION__, $domain, $response);

        return [
            'success' => true,
        ];
    } catch (Exception $e) {
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
        $eppClient = Store::getEpp();
        $domain = $eppClient->getDomain($domainName);
        $domain->removeNs($params['nameserver']);

        $response = $eppClient->updateDomain($domain);
        Store::log(__FUNCTION__, $domain, $response);

        return [
            'success' => true,
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_GetEPPCode($params)
{
    try {
        $eppClient = Store::getEpp();

        $requestData = [
            'domain'   => $params['domainname'],
            'authInfo' => null
        ];

        $domain = $eppClient->getDomain($requestData['domain'], $requestData['authInfo']);
        if (empty($domain->eppCode)) {
            $requestData['authInfo'] = 'request';
            $domain = $eppClient->getDomain($requestData['domain'], $requestData['authInfo']);
        }

        Store::log(__FUNCTION__, $requestData, ['eppcode' => $domain->eppCode, 'domain' => $domain]);

        return [
            'eppcode' => $domain->eppCode
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}

function domreg_ClientAreaCancelEPPCode($params)
{
    try {
        $eppClient = Store::getEpp();

        $requestData = [
            'domain'   => $params['domainname'],
            'authInfo' => 'cancel'
        ];

        $response = $eppClient->getDomain($requestData['domain'], $requestData['authInfo']);

        Store::log(__FUNCTION__, $requestData, $response);

        if (empty($response->eppCode)) {
            return [
                'vars' => [
                    'registrarCustomFunction' => [
                        'status'  => 'success',
                        'message' => 'domaincanceleppcodesuccess'
                    ]
                ]
            ];
        } else {
            return [
                'vars' => [
                    'registrarCustomFunction' => [
                        'status'  => 'success',
                        'message' => 'domaincanceleppcodeerror'
                    ]
                ]
            ];
        }
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'vars' => [
                'registrarCustomFunction' => [
                    'status'  => 'success',
                    'message' => 'domaincanceleppcodeerror'
                ]
            ]
        ];
    }
}

function domreg_CancelEPPCode($params)
{
    try {
        $eppClient = Store::getEpp();

        $requestData = [
            'domain'   => $params['domainname'],
            'authInfo' => 'cancel'
        ];

        $response = $eppClient->getDomain($requestData['domain'], $requestData['authInfo']);

        Store::log(__FUNCTION__, $requestData, $response);

        if (empty($response->eppCode)) {
            return [
                'success' => true,
                'message' => 'EPP code has been cancelled'
            ];
        } else {
            return [
                'error' => 'Failed to cancel EPP code'
            ];
        }
    } catch (Exception $e) {
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

        $syncNextDueDate = Capsule::table('tblconfiguration')
            ->where('setting', 'DomainSyncNextDueDateDays')
            ->value('value');

        $nextDueDate = $domain->expiresAt
            ->sub(new DateInterval('P' . $syncNextDueDate . 'D'))
            ->format('Y-m-d');

        $domainStatus = Store::convertDomregStatusToWhmcsStatus($domain->status);

        $domainDetails = [
            'success'          => true,
            'active'           => $domain->isActive(),
            'registrationdate' => $domain->createdAt->format('Y-m-d'),
            'expirydate'       => $domain->expiresAt->format('Y-m-d'),
            'nextduedate'      => $nextDueDate,
            'status'           => $domainStatus
        ];

        Store::log(__FUNCTION__, $domainName, $domainDetails);

        return $domainDetails;
    } catch (Exception $e) {
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
        $epp->retrieveMessages(function (Epp\Message $msg) {
            if ($msg->obType === 'domain'
                && $msg->notice === 'transferred'
            ) {
                $domainId = Capsule::table('tbldomains')
                                ->where('registrar', 'domreg')
                                ->where('domain',    $msg->object)
                                ->value('id');

                $userId = Capsule::table('tbldomains')
                            ->where('registrar', 'domreg')
                            ->where('id',        $domainId)
                            ->value('userid');

                localAPI('updateclientdomain', [
                    'domainid' => $domainId,
                    'status'   => 'Transferred Away',
                ]);

                logActivity('Domain: '.$msg->object.' - Domain ID: '.$domainId.' Set status to Transferred Away - Client ID: '.$userId, $userId);
            }

            return false;
        });

        // Get domain info
        $domain = $epp->getDomain($domainName);

        $syncNextDueDate = Capsule::table('tblconfiguration')
            ->where('setting', 'DomainSyncNextDueDateDays')
            ->value('value');

        $nextDueDate = $domain->expiresAt
            ->sub(new DateInterval('P' . $syncNextDueDate . 'D'))
            ->format('Y-m-d');

        $domainStatus = Store::convertDomregStatusToWhmcsStatus($domain->status);

        $domainDetails = [
            'success'     => true,
            'active'      => $domain->isActive(),
            'regdate'     => $domain->createdAt->format('Y-m-d'),
            'expirydate'  => $domain->expiresAt->format('Y-m-d'),
            'nextduedate' => $nextDueDate,
            'status'      => $domainStatus
        ];

        Store::log(__FUNCTION__, $domainName, $domain);

        return $domainDetails;
    } catch (Exception $e) {
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

        $syncNextDueDate = Capsule::table('tblconfiguration')
            ->where('setting', 'DomainSyncNextDueDateDays')
            ->value('value');

        $nextDueDate = $domain->expiresAt
                ->sub(new DateInterval('P' . $syncNextDueDate . 'D'))
                ->format('Y-m-d');

        $domainStatus = Store::convertDomregStatusToWhmcsStatus($domain->status);

        $domainDetails = [
            'domainid'    => $params['domainid'],
            'regdate'     => $domain->createdAt->format('Y-m-d'),
            'expirydate'  => $domain->expiresAt->format('Y-m-d'),
            'nextduedate' => $nextDueDate,
            'status'      => $domainStatus
        ];

        // Send the request
        $res = localAPI('UpdateClientDomain', $domainDetails, $params['AdminUser']);
        if ($res['result'] === 'error') {
            Store::log(__FUNCTION__, $domainDetails, $res);
            return [
                'error' => $res['message'],
            ];
        }

        Store::log(__FUNCTION__, $domainName, $domain);

        // Show a message with instructions
        return [
            'success' => true,
            'message' => '(Warning) You must refresh page to see the changes',
        ];
    } catch (Exception $e) {
        Store::log(__FUNCTION__, $params, null, $e);

        return [
            'error' => $e->getMessage(),
        ];
    }
}
