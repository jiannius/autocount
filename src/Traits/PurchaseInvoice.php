<?php

namespace Jiannius\Autocount\Traits;

use Illuminate\Support\Arr;

trait PurchaseInvoice
{
    /**
     * Create multiple purchase invoices
     * 
     * Payload structure
     * ==============================
     * 
     * [
     *     {
     *         "CreditorCode": "400-W003",//After u maintenance in Deptor maintenance u can ignore CreditorName,InvAddr1,2,3,4
     *         "CreditorName": "A001",
     *         //"InvAddr1": "Taman Setia Indah",
     *         //"InvAddr2": "Johor Bahru",
     *         //"InvAddr3": "Seri Austin",
     *         //"InvAddr4": "",
     *         "PIDTL": [
     *             {
     *                 "ItemCode": "002",
     *                 "Location": "HQ",
     *                 "Description": "TTT",
     *                 "Desc2":"",
     *                 "FurtherDescription": "testing",
     *                 //"ProjNo": "GOTGVOL03",
     *                 //"DeptNo": "D001",
     *                 "UnitPrice":"44",
     *                 // "UOM": "PCS",
     *                 "Qty": 2
     *             }
     *         ]
     *     }
     * ]
     */
    public function createPurchaseInvoices($data)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice',
            method: 'POST',
            data: $data,
        );

        return data_get($api->json(), 'ResultTable');
    }

    /**
     * Get purchase invoices
     * 
     * - date format - YYYY/MM/DD
     */
    public function getPurchaseInvoices($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/GetPurchaseInvoice',
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
     * Update multiple purchase invoices
     * 
     * - payload structure refer to create invoice
     */
    public function updatePurchaseInvoices($data)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/UpdatePurchaseInvoice',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete multiple purchase invoices
     */
    public function deletePurchaseInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/DeletePurchaseInvoice',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
            ],
        );

        return $api->json();
    }

    /**
     * Cancel multiple purchase invoices
     */
    public function cancelPurchaseInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/CancelPurchaseInvoice',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
                'Cancelled' => true,
            ],
        );

        return $api->json();
    }   
}