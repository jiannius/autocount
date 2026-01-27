<?php

namespace Jiannius\Autocount\Traits;

trait DebitNote
{
    /**
     * Create debit note
     * 
     * Payload structure
     * -----------------
     * [
     *     {
     *         "DebtorCode": "300-A001",        // After u maintenance in Deptor maintenance u can ignore DebtorName,InvAddr1,2,3,4
     *         "DebtorName": "xyz78901",
     *         "InvAddr1": "123 Oak Street",
     *         "InvAddr2": "Apt 305",
     *         "InvAddr3": "Greenwood",
     *         "InvAddr4": "WA 98001",
     *         "BranchCode": "BR007",
     *         "Validity": "45 days",
     *         "CC": "USD",
     *         "DisplayTerm": "Net 30",
     *         "DeliveryTerm": "DAP",
     *         "PaymentTerm": "Bank Transfer",
     *         "SalesAgent": "Jane Doe",
     *         "ShipVia": "FedEx",
     *         "ShipInfo": "Standard Shipping",
     *         "SalesLocation": "Warehouse G",
     *         "MultiPrice": "No",
     *         "DocStatus":"A",
     *         "SubmitEInvoice": "T",
     *         "ConsolidatedEInvoice":"F",
     *         "DNDTL": [
     *             {
     *                 "ItemCode": "001",
     *                 "UnitPrice": "100",
     *                 "Qty": "10"
     *             }
     *         ]
     *     }
     * ] 
     */
    public function createDebitNote($data)
    {
        $api = $this->callApi(
            uri: 'DebitNote',
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
     * Get debit notes
     */
    public function getDebitNotes($numbers = null, $from = null, $to = null)
    {
        try {
            $api = $this->callApi(
                uri: 'DebitNote/GetDebitNote',
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
     * Update debit note
     */
    public function updateDebitNote($data)
    {
        $api = $this->callApi(
            uri: 'DebitNote/UpdateDebitNote',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple debit notes
     */
    public function deleteDebitNotes($numbers)
    {
        $api = $this->callApi(
            uri: 'DebitNote/DeleteDebitNote',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel multiple debit notes
     */
    public function cancelDebitNotes($numbers, $reason)
    {
        $api = $this->callApi(
            uri: 'DebitNote/CancelDebitNote',
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