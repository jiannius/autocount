<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait CashBook
{
    /**
     * Create Cash Book
     * 
     * Payload structure
     * -----------------
     * [
     *     {
     *         "DocNo": "",
     *         //"DocDate": "",
     *         "DocType": "PV", //(PV/OR)
     *         "DealWith": "", //only if u want to write by urself, if using TIN/TaxEntityID then no need write here
     *         "Description": "",
     *         //"CurrencyCode": "",
     *         "TIN": "",
     *         "TaxEntityID": "",
     *         "DocStatus": "",
     *         "CBDTL": [
     *             {
     *                 "AccNo": "", //Compulsory        
     *                 // "ToAccountRate": "",
     *                 // "Description": "",
     *                 // "FurtherDescription": "",
     *                 // "ProjNo": "",
     *                 // "DeptNo": "",
     *                 "Amount": ""
     *                 // "TaxCode": "",
     *                 // "TaxRate": "",
     *                 // "TaxableAmt": "",
     *                 // "TaxAdjustment": "",
     *                 // "LocalTaxAdjustment": "",
     *                 // "TaxPermitNo": "", //Tax Details
     *                 // "TaxExportCountry": "", //Tax Details
     *                 // "TaxRefNo": "", //Tax Details
     *                 // "TaxBillDate": "", //Tax Details
     *                 // "RCHQAmount": "",
     *                 // "InclusiveTax": ""
     *             }
     *         ],
     *         "CBPaymentDTL": [
     *             {
     *                 "PaymentMethod": "", //Compulsory
     *                 //"ChequeNo": "",
     *                 "PaymentAmt": "" //Compulsory
     *                 // "BankCharge": "",
     *                 // "BankChargeTaxCode": "",
     *                 // "BankChargeTax": "",
     *                 // "BankChargeTaxRefNo": "",
     *                 // "ToBankRate": "",
     *                 // "PaymentBy": "",
     *                 // "IsRCHQ": "",
     *                 // "RCHQDate": ""
     *             }
     *         ]
     *     }
     * ]
    */
    public function createCashBook($data)
    {
        $api = $this->callApi(
            uri: 'CashBook',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get Cash Book
     */
    public function getCashBooks($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'CashBook/GetCashBook',
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
     * Update Cash Book
     */
    public function updateCashBook($data)
    {
        $api = $this->callApi(
            uri: 'CashBook/UpdateCashBook',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete CashBook
     */
    public function deleteCashBooks($numbers)
    {
        $api = $this->callApi(
            uri: 'CashBook/DeleteCashBook',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel CashBook
     */
    public function cancelCashBooks($numbers)
    {
        $api = $this->callApi(
            uri: 'CashBook/CancelCashBook',
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