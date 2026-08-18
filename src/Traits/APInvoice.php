<?php

namespace Jiannius\Autocount\Traits;

trait APInvoice
{
    /**
     * Create AP Invoice
     *
     * The AP Invoice is the AP-ledger (financial) invoice, NOT the item-based
     * Purchase Invoice (see Traits/PurchaseInvoice.php). Its line items are
     * GL-account based: each detail row posts an Amount to an account
     * ("Purchase A/C" in the AutoCount UI), not a stock item.
     *
     * Payload structure
     * -----------------
     * {
     *     "DocNo": "PI-000001",
     *     "CreditorCode": "400-A001",
     *     "Description": "",
     *     "DocDate": "",              // YYYY/MM/DD
     *     "JournalType": "PURCHASE",  // matches the "Journal Type" dropdown (PURCHASE, Bank, ...)
     *     "CurrencyCode": "MYR",
     *     "RefNo2": "",               // "Ref. No. 2" field
     *     "Agent": "",
     *     "CreditTerm": "",           // e.g. "Net 30 days", "C.O.D."
     *     "InclusiveTax": false,      // the "Inclusive?" checkbox
     *     "APInvoiceDTL": [
     *         {
     *             "AccNo": "5005-2000",   // the "Purchase A/C" column
     *             "Description": "",      // line description
     *             "Amount": 1018.49,
     *             "TaxCode": ""           // e.g. "TX-6"
     *         }
     *     ]
     * }
     *
     * Notes
     * -----
     * - Detail array key `APInvoiceDTL` follows this package's API convention
     *   (cf. APCNDTL, APDNDTL, ARInvoiceDTL). The newer AOTG public-v1 API
     *   documented on the AutoCount wiki uses `DetailsLine` + `LineState`
     *   instead; that is a different API generation and does NOT apply here.
     * - `$data` is a single document; it is wrapped into the array the API
     *   expects, mirroring createARInvoice().
     */
    public function createAPInvoice($data)
    {
        $api = $this->callApi(
            uri: 'APInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get AP Invoices
     */
    public function getAPInvoices($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'APInvoice/GetAPInvoice',
                method: 'POST',
                data: ['DocNo' => array_filter((array) $numbers)],
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if ($this->isRecordNotFound($e)) return [];
            else throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update AP Invoice
     *
     * - payload structure refer to createAPInvoice
     */
    public function updateAPInvoice($data)
    {
        $api = $this->callApi(
            uri: 'APInvoice/UpdateAPInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete AP Invoices
     *
     * - endpoint inferred from sibling resources (APCreditNote, ARInvoice);
     *   not present in the Postman collection but follows the same convention.
     */
    public function deleteAPInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'APInvoice/DeleteAPInvoice',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel AP Invoices
     *
     * - endpoint inferred from sibling resources (APCreditNote, ARInvoice);
     *   not present in the Postman collection but follows the same convention.
     */
    public function cancelAPInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'APInvoice/CancelAPInvoice',
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
