<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait APCN
{
    /**
     * Create APCN
     * 
     * Payload structure
     * -----------------
     * {
     *     "CreditorCode": "4000/A01",
     *     "Description": "",
     *     "JournalType":"PURCHASE",
     *     "APCNDTL": [
     *         {
     *             "AccNo": "2003/000",
     *             "PaymentBy": "",
     *             "Amount": 10
     *      
     *         }
     *     ],
     *     "APCNKnockOff": [
     *         {
     *             "DocNo": "PI-2207/077",
     *             "KnocOffAmount": 10
     *         }
     *     ]
     * }
     */
    public function createAPCN($data)
    {
        $api = $this->callApi(
            uri: 'APCreditNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get APCN
     */
    public function getAPCNs($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'APCreditNote/GetAPCreditNote',
                method: 'POST',
                data: ['DocNo' => array_filter((array) $numbers)],
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if (str($e->getMessage())->slug()->is('*not-found*')) return [];
            else throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update APCN
     */
    public function updateAPCN($data)
    {
        $api = $this->callApi(
            uri: 'APCreditNote/UpdateAPCreditNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete APCN
     */
    public function deleteAPCNs($numbers)
    {
        $api = $this->callApi(
            uri: 'APCreditNote/DeleteAPCreditNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel APCN
     */
    public function cancelAPCNs($numbers)
    {
        $api = $this->callApi(
            uri: 'APCreditNote/CancelAPCreditNote',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
                'Cancelled' => true,
            ],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }
}