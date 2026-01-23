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
    public function createARPayments($data)
    {
        $api = $this->callApi(
            uri: 'ARPayment',
            method: 'POST',
            data: $data,
        );

        return data_get($api->json(), 'ResultTable.0');
    }

    /**
     * Get AR payments
     * 
     * - date format - YYYY/MM/DD
     */
    public function getARPayments($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'ARPayment/GetARPayment',
            method: 'POST',
            data: array_filter([
                'DocNo' => array_filter((array) $numbers),
                'DateFrom' => $from,
                'DateTo' => $to,
            ]),
        );

        return data_get($api->json(), 'ResultTable');
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

        return data_get($api->json(), 'ResultTable.0');
    }

    /**
     * Delete multiple AR payments
     */
    public function deleteARPayments($numbers)
    {
        $api = $this->callApi(
            uri: 'ARPayment/DeleteARPayment',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
            ],
        );

        return data_get($api->json(), 'ResultTable');
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

        return data_get($api->json(), 'ResultTable');
    }   
}