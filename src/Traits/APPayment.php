<?php

namespace Jiannius\Autocount\Traits;

trait APPayment
{
    /**
     * Create AP payment
     * 
     * Payload structure
     * -----------------
     * {
     *     "CreditorCode": "KWP001",
     *     "Description": "",
     *     //"ProjNo": "GOTGVOL03",
     *     //"DeptNo": "D001",
     *     "APPaymentDTL": [
     *         {
     *             "PaymentMethod": "CASH",
     *             //"PaymentBy": "",
     *             "PaymentAmt": 15.0
     *             //"ChequeNo":"",
     *             //"FloatDay": 0,
     *             //"BankCharge": 0.00,
     *             //"IsRCHQ": "F",
     *             //"RCHQDate": ""
     *         }
     *     ],
     *     "APPaymentKnockOff": [
     *         {
     *             "DocNo": "CP-000006",
     *             "KnocOffAmount": 5.0,
     *             "KnockOffDocType": "P"
     *             // PB = Invoice
     *             // PD = Debit Note
     *         }
     *     ]
     * }
     */
    public function createAPPayment($data)
    {
        $api = $this->callApi(
            uri: 'APPayment',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get AP payments
     * 
     * - date format - YYYY/MM/DD
     */
    public function getAPPayments($numbers = null, $from = null, $to = null)
    {
        try {
            $api = $this->callApi(
                uri: 'APPayment/GetAPPayment',
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
     * Update AP payment
     */
    public function updateAPPayment($data)
    {
        $api = $this->callApi(
            uri: 'APPayment/UpdateAPPayment',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple AP payments
     */
    public function deleteAPPayments($numbers)
    {
        $api = $this->callApi(
            uri: 'APPayment/DeleteAPPayment',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }
}