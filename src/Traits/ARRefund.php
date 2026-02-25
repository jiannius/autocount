<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait ARRefund
{
    /**
     * Create ARRefund
     * 
     * Payload structure
     * -----------------
     * {
     *     "DebtorCode": "3000/001",
     *     "Description": "",
     *     "ARRefundDTL": [
     *         {
     *             "PaymentMethod": "CASH IN HAND",
     *             "PaymentAmt": 10.0
     *         }
     *     ],
     *     "ARRefundKnockOff": [
     *         {
     *             "DocNo": "OR-2404-001",
     *             "KnocOffAmount": 10
     *         }
     *     ]
     * }
     */
    public function createARRefund($data)
    {
        $api = $this->callApi(
            uri: 'ARRefund',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get ARRefund
     */
    public function getARRefunds($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARRefund/GetARRefund',
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
     * Update ARRefund
     */
    public function updateARRefund($data)
    {
        $api = $this->callApi(
            uri: 'ARRefund/UpdateARRefund',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete ARRefund
     */
    public function deleteARRefunds($numbers)
    {
        $api = $this->callApi(
            uri: 'ARRefund/DeleteARRefund',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel ARRefund
     */
    public function cancelARRefunds($numbers)
    {
        $api = $this->callApi(
            uri: 'ARRefund/CancelARRefund',
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