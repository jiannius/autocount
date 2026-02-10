<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait ARPayment
{
    /**
     * Create AR payment
     * 
     * Payload structure
     * -----------------
     * [
     *  "DebtorCode": "300-A001",
     *  "Description": "", 
     *  "ProjNo": "GOTGVOL03",
     *  "DeptNo": "D001",
     *  "ARPaymentDTL": [
     *      {
     *          "PaymentMethod": "CASH",
     *          "PaymentAmt": 100,
     *          "PaymentBy": "",
     *          "ChequeNo":"",
     *          "FloatDay": 0,
     *          "BankCharge": 0.00,
     *          "IsRCHQ": "F",
     *          "RCHQDate": ""
     *      }
     *  ],
     *  "ARPaymentKnockOff": [
     *      {
     *          "DocNo": "I-000004",
     *          "KnockOffAmount": 145 ,//Can not be PaymentAmt > Outstanding 
     *          "KnockOffDocType": "RI"
     *          // RI = INVOICE
     *          // RD = DEBIT NOTE
     *      }
     *  ]
     * ]
     */
    public function createARPayment($data)
    {
        $api = $this->callApi(
            uri: 'ARPayment',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get AR payments
     * 
     * - date format - YYYY/MM/DD
     */
    public function getARPayments($numbers = null, $from = null, $to = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARPayment/GetARPayment',
                method: 'POST',
                data: array_filter([
                    'DocNo' => array_filter((array) $numbers),
                    'DateFrom' => $from,
                    'DateTo' => $to,
                ]),
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if (str($e->getMessage())->slug()->is('*not-found*')) return [];
            else throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update AR payment
     * 
     * - payload structure refer to create payment
     */
    public function updateARPayment($data)
    {
        $api = $this->callApi(
            uri: 'ARPayment/UpdateARPayment',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple AR payments
     */
    public function deleteARPayments($numbers)
    {
        $api = $this->callApi(
            uri: 'ARPayment/DeleteARPayment',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel multiple AR payments
     */
    public function cancelARPayments($numbers)
    {
        $api = $this->callApi(
            uri: 'ARPayment/CancelARPayment',
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