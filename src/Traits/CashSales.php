<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait CashSales
{
    /**
     * Create multiple cash sales
     * 
     * Payload structure
     * -----------------
     * [
     *   {
     *       "DEBTORCODE": "300-A001",          // After u maintenance in Deptor maintenance u can ignore DebtorName,InvAddr1,2,3,4
     *       "DebtorName": "",
     *       "InvAddr1": "",
     *       "InvAddr2": "",
     *       "InvAddr3": "",
     *       "InvAddr4": "",
     *       "BranchCode": "",
     *       "Validity": "",
     *       "CC": "",
     *       "DisplayTerm": "",
     *       "DeliveryTerm": "",
     *       "PaymentTerm": "",
     *       "SalesAgent": "",
     *       "ShipVia": "",
     *       "ShipInfo": "",
     *       "SalesLocation": "",
     *       "MultiPrice": "",
     *       "ItemGroup": "",
     *       "SubmitEInvoice": "T",             // T / F
     *       "ConsolidatedEInvoice": "F",       // T / F
     *       "CSDTL": [
     *           {
     *               "ItemCode": "001",         // After u maintenance in Stock Item Maintenance u can ignore Description, Desc2, UnitPrice
     *               "Description": "",         // Required if ItemCode not provided
     *               "Desc2": "",
     *               "FurtherDescription": "",
     *               "DeliveryDate": "",
     *               "Location": "",
     *               "DeptNo": "",
     *               "ProjNo": "",
     *               "UOM": "",
     *               "UnitPrice": 7,
     *               "Qty": "10"
     *               "Discount": "",
     *               "SalesExemptionNo": ""
     *           }
     *       ],
     *       "PaymentMethod": "CASH",
     *       "PaymentAmt": 100
     *   }
     * ]
     */
    public function createCashSales($data)
    {
        $api = $this->callApi(
            uri: 'CashSales',
            method: 'POST',
            data: Arr::map($data, fn ($sale) => [
                'SubmitEInvoice' => 'T',
                'ConsolidatedEInvoice' => 'F',
                ...$sale,
            ]),
        );

        return $api->json();
    }

    /**
     * Get cash sales
     */
    public function getCashSales($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'CashSales/GetCashSales',
            method: 'POST',
            data: [
                'DocNo' => array_filter([$numbers]),
                'DateFrom' => $from,
                'DateTo' => $to,
            ],
        );

        return $api->json();
    }

    /**
     * Update multiple cash sales
     * 
     * - payload structure refer to create cash sales
     */
    public function updateCashSales($data)
    {
        $api = $this->callApi(
            uri: 'CashSales/UpdateCashSales',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete multiple cash sales
     */
    public function deleteCashSales($numbers)
    {
        $api = $this->callApi(
            uri: 'CashSales/DeleteCashSales',
            method: 'POST',
            data: [
                'DocNo' => $numbers,
            ],
        );

        return $api->json();
    }

    /**
     * Cancel multiple cash sales
     */
    public function cancelCashSales($numbers, $reason)
    {
        $api = $this->callApi(
            uri: 'CashSales/CancelCashSales',
            method: 'POST',
            data: [
                'DocNo' => $numbers,
                'EinvoiceCancelReason' => $reason,
            ],
        );

        return $api->json();
    }   
}