<?php
/**
 * Copyright (c) 2018 Aleksej Komarov
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

use WHMCS\Database\Capsule;
use Domreg\Epp;
use Domreg\Store;

require_once __DIR__ . '/domreg.php';

function __domreg_RenderFields($fields) {
    $result = [];
    foreach ($fields as $i => $f) {
        $value = isset($f['value']) && $f['value']
            ? $f['value']
            : '';
        $hint = isset($f['hint']) && $f['hint']
            ? '&nbsp;' . $f['hint']
            : '';
        $disabled = isset($f['disabled']) && $f['disabled']
            ? 'disabled'
            : '';
        $result[$f['label']] = '<input type="text" '
            . 'name="' . $i . '" '
            . 'value="' . $value . '" '
            . $disabled . '>' . $hint;
    }
    return $result;
}

add_hook('AdminClientDomainsTabFields', 1, function ($params) {
    // Get domain
    $domainRow = Capsule::table('tbldomains')
        ->where('id', $params['id'])
        ->first();
    // Don't do anything if there's no domain or registrar is not Domreg
    if (!$domainRow || $domainRow->registrar !== 'domreg') {
        return false;
    }
    // Set up fields
    $fields = [
        'domain_rn' => [
            'label' => 'Domain RN',
            'hint' => 'RN associated with this domain',
            'disabled' => true,
        ],
        'client_rn' => [
            'label' => 'Client RN',
            'hint' => 'RN for registering new domains',
        ],
    ];
    // Retrieve Domain RN
    try {
        $epp = Store::getEpp();
        $domain = $epp->getDomain($domainRow->domain);
        $fields['domain_rn']['value'] = $domain->registrant;
    } catch (Exception $e) {
        $fields['domain_rn']['hint'] = 'Error: ' . $e->getMessage();
    }
    // Retrieve Client RN
    $fields['client_rn']['value'] = Store::getClientRn($domainRow->userid);
    // Render
    return __domreg_RenderFields($fields);
});

add_hook('AdminClientDomainsTabFieldsSave', 1, function ($params) {
    // Get domain
    $domainRow = Capsule::table('tbldomains')
        ->where('id', $params['id'])
        ->first();
    // Don't do anything if there's no domain or registrar is not Domreg
    if (!$domainRow || $domainRow->registrar !== 'domreg') {
        return false;
    }
    // Save Client RN
    Store::setClientRn($domainRow->userid, $params['client_rn']);
    return $params;
});
