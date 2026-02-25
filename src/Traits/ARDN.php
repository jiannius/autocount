<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait ARDN
{
    /**
     * Create ARDN
     * 
     * Payload structure
     * -----------------
     * {
     *     "DebtorCode": "3000/A02",
     *     "Description": "",
     *     "JournalType":"SALES",
     *     "ARDNDTL": [
     *         {
     *             "AccNo": "2002/000",            
     *             "Amount": 10     
     *         }
     *     ]
     * }
     */
    public function createARDN($data)
    {
        $api = $this->callApi(
            uri: 'ARDebitNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get ARDN
     */
    public function getARDNs($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARDebitNote/GetARDebitNote',
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
     * Update ARDN
     */
    public function updateARDN($data)
    {
        $api = $this->callApi(
            uri: 'ARDebitNote/UpdateARDebitNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete ARDN
     */
    public function deleteARDNs($numbers)
    {
        $api = $this->callApi(
            uri: 'ARDebitNote/DeleteARDebitNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel ARDN
     */
    public function cancelARDNs($numbers)
    {
        $api = $this->callApi(
            uri: 'ARDebitNote/CancelARDebitNote',
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