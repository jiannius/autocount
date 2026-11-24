<?php

namespace Jiannius\Autocount\Traits;

use Jiannius\Autocount\Exceptions\AutocountRefused;

/**
 * AR/AP Contra Entry: knocks a debtor's documents off against its creditor twin's
 * documents through a journal voucher.
 *
 * The provider sent only two samples (create and update), no docs. Everything else
 * here is ASSUMED to follow the convention of the other document resources (ARDN).
 * If a call 404s or returns an unexpected shape, check the matching guess below:
 *
 * - KNOWN:   create is POST ARAPContraEntry (sample had a trailing slash; sent without, like every other resource).
 * - KNOWN:   update path is ARAPContraEntry/UpdateARAPContraEntry with the payload plus "DocNo".
 * - ASSUMED: update is POST (the sample did not show the method).
 * - ASSUMED: get is POST ARAPContraEntry/GetARAPContraEntry with {"DocNo": [...]}.
 * - ASSUMED: delete is POST ARAPContraEntry/DeleteARAPContraEntry with {"DocNo": [...]}.
 * - ASSUMED: cancel is POST ARAPContraEntry/CancelARAPContraEntry with {"DocNo": [...], "Cancelled": true}.
 * - ASSUMED: responses have the same shape as ARDN's (Status / Message, created DocNo included).
 * - ASSUMED: header fields DocNo, DocDate, Description (and the rest ARDN takes) are accepted on create/update.
 * - ASSUMED: "not found" on get comes back with the same wording isRecordNotFound() already recognises.
 */
trait ARAPContraEntry
{
    /**
     * Create AR/AP contra entry
     *
     * Payload structure (provider sample; DocDate/Description/DocNo assumed, as on ARDN)
     * ----------------------------------------------------------------------------------
     * {
     *     "DocNo": "<<New>>",
     *     "DocDate": "2026-11-24",
     *     "Description": "",
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
     * Get AR/AP contra entries
     */
    public function getARAPContraEntries($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARAPContraEntry/GetARAPContraEntry',
                method: 'POST',
                data: ['DocNo' => array_filter((array) $numbers)],
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if ($this->isRecordNotFound($e)) return [];
            else throw $e;
        }
    }

    /**
     * Update AR/AP contra entry
     *
     * Payload structure (provider sample)
     * -----------------------------------
     * Same as create, with the journal voucher's DocNo:
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

    /**
     * Delete AR/AP contra entries
     */
    public function deleteARAPContraEntries($numbers)
    {
        $api = $this->callApi(
            uri: 'ARAPContraEntry/DeleteARAPContraEntry',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', AutocountRefused::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel AR/AP contra entries
     */
    public function cancelARAPContraEntries($numbers)
    {
        $api = $this->callApi(
            uri: 'ARAPContraEntry/CancelARAPContraEntry',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
                'Cancelled' => true,
            ],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', AutocountRefused::class, data_get($result, 'Message'));

        return $result;
    }
}
