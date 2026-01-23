<?php

namespace Jiannius\Autocount\Traits;

trait Invoice
{
    /**
     * Create multiple invoices
     * 
     * Payload structure
     * -----------------
     * [
     *  [
     *      "DEBTORCODE": "300-A001",       //After u maintenance in Deptor maintenance u can ignore DebtorName,InvAddr1,2,3,4
     *      "DocNoFormatName":"IV GG01",
     *      "DebtorName": "",
     *      "InvAddr1": "",
     *      "InvAddr2": "",
     *      "InvAddr3": "",
     *      "InvAddr4": "",
     *      "BranchCode": "",
     *      "Validity": "",
     *      "CC": "",
     *      "DisplayTerm": "",
     *      "DeliveryTerm": "",
     *      "PaymentTerm": "",
     *      "SalesAgent": "",
     *      "ShipVia": "",
     *      "ShipInfo": "",
     *      "SalesLocation": "",
     *      "MultiPrice": "",
     *      "ItemGroup": "",
     *      "DocStatus": "A",               // (D) Draft, (A) Approved, (P) Awaiting Approval, (E) Expired, (R) Rejected, (V) Void
     *      "SubmitEInvoice": "T",          // T / F
     *      "ConsolidatedEInvoice": "F",    // T / F
     *      "IVDTL": [
     *          {
     *              "ItemCode": "001",      //After u maintenance in Stock Item Maintenance u can ignore Description, Desc2, UnitPrice,Classification
     *              "Description": "",      // Required if ItemCode not provided
     *              "Desc2": "",
     *              "Classification":"",    // Required for E-Invoice submission
     *              "UnitPrice": "",
     *              "FurtherDescription": "",
     *              "DeliveryDate": "",
     *              "Location": "",
     *              "DeptNo": "",
     *              "ProjNo": "",
     *              "UOM": "",
     *              "Qty": "10",
     *              "Discount": "",
     *              "TaxCode": "",
     *              "SalesExemptionNo": ""
     *          }
     *      ]
     *  ]
     * ]
     * 
     * E-invoice submission
     * ---------------------
     * - SubmitEinvoice: T ; DocStatus: D wont submit
     * - SubmitEinvoice: F ; DocStatus: A wont submit
     * - SubmitEinvoice: T and DocStatus: A it will Submit
     * 
     */
    public function createInvoice($data)
    {
        $api = $this->callApi(
            uri: 'Invoice',
            method: 'POST',
            data: [[
                'DocStatus' => 'A',
                'SubmitEInvoice' => 'F',
                'ConsolidatedEInvoice' => 'F',
                ...$data,
            ]],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get invoices
     * 
     * - date format - YYYY/MM/DD
     */
    public function getInvoices($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'Invoice/GetInvoice',
            method: 'POST',
            data: array_filter([
                'DocNo' => array_filter((array) $numbers),
                'DateFrom' => $from,
                'DateTo' => $to,
            ]),
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Update multiple invoices
     * 
     * - payload structure refer to create invoice
     */
    public function updateInvoice($data)
    {
        $api = $this->callApi(
            uri: 'Invoice/UpdateInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple invoices
     */
    public function deleteInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'Invoice/DeleteInvoice',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel multiple invoices
     */
    public function cancelInvoices($numbers, $reason)
    {
        $api = $this->callApi(
            uri: 'Invoice/CancelInvoice',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
                'EinvoiceCancelReason' => $reason,
            ],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }
}