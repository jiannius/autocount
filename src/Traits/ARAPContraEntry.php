<?php

namespace Jiannius\Autocount\Traits;

use Jiannius\Autocount\Exceptions\AutocountRefused;

/**
 * AR/AP Contra Entry: knocks a debtor's documents off against its creditor twin's
 * documents through a journal voucher.
 *
 * Only create and update are known to exist upstream (provider samples, no docs).
 * Get / Delete / Cancel are not added until the provider confirms their endpoints.
 */
trait ARAPContraEntry
{
    /**
     * Create AR/AP contra entry
     *
     * Payload structure (provider sample)
     * -----------------------------------
     * {
     *     "DebtorCode": "300-0001",
     *     "CreditorCode": "400-2002",
     *     "JournalType": "GENERAL",
     *     "ARContraKnockOff": [
     *         {
     *             "DocNo": "I-000069",
     *             "Amount": 1.00
     *         }
     *     ],
     *     "APContraKnockOff": [
     *         {
     *             "DocNo": "PI-000059",
     *             "Amount": 1.00
     *         }
     *     ]
     * }
     */
    public function createARAPContraEntry($data)
    {
        $api = $this->callApi(
            uri: 'ARAPContraEntry',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', AutocountRefused::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Update AR/AP contra entry
     *
     * Payload structure (provider sample)
     * -----------------------------------
     * Same as create, plus the journal voucher's DocNo:
     * {
     *     "DocNo": "JV-000016",
     *     "DebtorCode": "300-0001",
     *     "CreditorCode": "400-2002",
     *     "JournalType": "GENERAL",
     *     "ARContraKnockOff": [{ "DocNo": "I-000069", "Amount": 1.00 }],
     *     "APContraKnockOff": [{ "DocNo": "PI-000059", "Amount": 1.00 }]
     * }
     */
    public function updateARAPContraEntry($data)
    {
        $api = $this->callApi(
            uri: 'ARAPContraEntry/UpdateARAPContraEntry',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', AutocountRefused::class, data_get($result, 'Message'));

        return $result;
    }
}
