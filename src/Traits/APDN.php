<?php

namespace Jiannius\Autocount\Traits;

trait APDN
{
    /**
     * Create APDN
     *
     * Payload structure
     * -----------------
     * {
     *     "CreditorCode": "4000/A02",
     *     "Description": "",
     *     "JournalType": "PURCHASE",
     *     "APDNDTL": [
     *         {
     *             "AccNo": "2003/000",
     *             "Amount": 10
     *         }
     *     ]
     * }
     */
    public function createAPDN($data)
    {
        $api = $this->callApi(
            uri: 'APDebitNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get APDN
     */
    public function getAPDNs($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'APDebitNote/GetAPDebitNote',
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
     * Update APDN
     */
    public function updateAPDN($data)
    {
        $api = $this->callApi(
            uri: 'APDebitNote/UpdateAPDebitNote',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete APDN
     */
    public function deleteAPDNs($numbers)
    {
        $api = $this->callApi(
            uri: 'APDebitNote/DeleteAPDebitNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel APDN
     */
    public function cancelAPDNs($numbers)
    {
        $api = $this->callApi(
            uri: 'APDebitNote/CancelAPDebitNote',
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