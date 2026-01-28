<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait ARCN
{
    /**
     * Create ARCN
     * 
     * Payload structure
     * -----------------
     * {
     *     "DebtorCode": "3000/A02",
     *     "Description": "",
     *     "JournalType":"SALES",
     *     "ARCNDTL": [
     *         {
     *             "AccNo": "2002/000",
     *             "PaymentBy": "",
     *             "Amount": 10
     *      
     *         }
     *     ],
     *     "ARCNKnockOff": [
     *         {
     *             "DocNo": "INV0384/23",
     *             "KnocOffAmount": 10
     *         }
     *     ]
     * }
     */
    public function createARCN($data)
    {
        $api = $this->callApi(
            uri: 'ARCreditNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get ARCN
     */
    public function getARCNs($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARCreditNote/GetARCreditNote',
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
     * Update ARCN
     */
    public function updateARCN($data)
    {
        $api = $this->callApi(
            uri: 'ARCreditNote/UpdateARCreditNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete ARCN
     */
    public function deleteARCNs($numbers)
    {
        $api = $this->callApi(
            uri: 'ARCreditNote/DeleteARCreditNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel ARCN
     */
    public function cancelARCNs($numbers)
    {
        $api = $this->callApi(
            uri: 'ARCreditNote/CancelARCreditNote',
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