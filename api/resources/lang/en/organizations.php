<?php

return [
    'errors' => [
        'last_canal'     => 'This is the last canal of the company. Assign another one first, otherwise you would lose access to the company.',
        'canal_required' => 'Select the canal the organization should belong to.',
    ],

    'account' => [
        'disabled'        => 'The Account integration is not configured.',
        'lookup_timeout'  => 'The register did not answer in time. Try again or fill the data in manually.',
        'lookup_failed'   => 'The register is currently unavailable.',
        'unavailable'     => 'The billing data could not be saved — Account is not responding. Try again in a moment.',
        'upstream_failed' => 'The billing data could not be saved — Account replied with an error (HTTP :status). Details are in the Event log.',
    ],

    'webhook' => [
        'disabled' => 'Webhooks from Account are not configured.',
        'invalid_signature' => 'Invalid signature.',
        'stale' => 'Stale request.',
    ],
];
