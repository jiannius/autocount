<?php

namespace Jiannius\Autocount\Traits;

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
    public function createPurchaseInvoice($data)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get purchase invoices
     * 
     * - date format - YYYY/MM/DD
     */
    public function getPurchaseInvoices($numbers = null, $from = null, $to = null)
    {
        try {
            $api = $this->callApi(
                uri: 'PurchaseInvoice/GetPurchaseInvoice',
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
     * Update multiple purchase invoices
     * 
     * - payload structure refer to create invoice
     */
    public function updatePurchaseInvoice($data)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/UpdatePurchaseInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple purchase invoices
     */
    public function deletePurchaseInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'PurchaseInvoice/DeletePurchaseInvoice',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
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

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }   
}