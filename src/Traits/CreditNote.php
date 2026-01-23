<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait CreditNote
{
    /**
     * Create credit note
     * 
     * Payload structure
     * -----------------
     * [
     *     {
     *         "DEBTORCODE": "300-A001",            // After u maintenance in Deptor maintenance u can ignore DebtorName,InvAddr1,2,3,4
     *         "memberNo": "M-0001",
     *         "DebtorName": "",
     *         "InvAddr1": "",
     *         "InvAddr2": "",
     *         "InvAddr3": "",
     *         "InvAddr4": "",
     *         "BranchCode": "",
     *         "Validity": "",
     *         "CC": "",
     *         "DisplayTerm": "",
     *         "DeliveryTerm": "",
     *         "PaymentTerm": "",
     *         "SalesAgent": "",
     *         "ShipVia": "",
     *         "ShipInfo": "",
     *         "SalesLocation": "",
     *         "MultiPrice": "",
     *         "ItemGroup": "",
     *         "UDF_Name1": "ac",
     *         "DocStatus": "D",
     *         "SubmitEInvoice": "T",
     *         "ConsolidatedEInvoice": "F",
     *         "CNDTL": [
     *             {
    *                 "ItemCode": "001",             // After u maintenance in Stock Item Maintenance u can ignore Description, Desc2, UnitPrice,Classification
     *                 "UOM": "PCS",
     *                 "Description": "",
     *                 "Desc2": "",
     *                 "Classification":"",
     *                 "FurtherDescription": "A high-quality widget suitable for various applications.",
     *                 "DeliveryDate": "2024-09-15",
     *                 "Location": "",
     *                 "DeptNo": "",
     *                 "ProjNo": "",
     *                 "Qty": "10",
     *                 "UnitPrice": "99",
     *                 "Discount": "5",
     *                 "TaxCode": "GST-6",
     *                 "SalesExemptionNo": "12345"
     *             }
     *         ]
     *     }
     * ]
     */
    public function createCreditNote($data)
    {
        $api = $this->callApi(
            uri: 'CreditNote',
            method: 'POST',
            data: [[
                'DocStatus' => 'A',
                'SubmitEInvoice' => 'T',
                'ConsolidatedEInvoice' => 'F',
                ...$data,
            ]],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get credit notes
     */
    public function getCreditNotes($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'CreditNote/GetCreditNote',
            method: 'POST',
            data: array_filter([
                'DocNo' => array_filter((array) $numbers),
                'DateFrom' => $from,
                'DateTo' => $to,
            ]),
        );

        return $api->json();
    }

    /**
     * Update credit note
     */
    public function updateCreditNote($data)
    {
        $api = $this->callApi(
            uri: 'CreditNote/UpdateCreditNote',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple credit notes
     */
    public function deleteCreditNotes($numbers)
    {
        $api = $this->callApi(
            uri: 'CreditNote/DeleteCreditNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel multiple credit notes
     */
    public function cancelCreditNotes($numbers, $reason)
    {
        $api = $this->callApi(
            uri: 'CreditNote/CancelCreditNote',
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