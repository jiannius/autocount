<?php

namespace Jiannius\Autocount\Traits;

trait ARInvoice
{
    /**
     * Create AR Invoice
     *
     * The AR Invoice is the AR-ledger (financial) invoice, NOT the item-based
     * Sales Invoice (see Traits/Invoice.php). Its line items are GL-account
     * based: each detail row posts an Amount to an account ("Sales A/C" in the
     * AutoCount UI), not a stock item.
     *
     * Payload structure
     * -----------------
     * {
     *     "DocNo": "I-000001",
     *     "DebtorCode": "400-A001",
     *     "Description": "",
     *     "DocDate": "",              // YYYY/MM/DD
     *     "JournalType": "SALES",     // matches the "Journal Type" dropdown (SALES, Bank, ...)
     *     "CurrencyCode": "MYR",
     *     "RefNo2": "",               // "Ref. No. 2" field
     *     "Agent": "",
     *     "CreditTerm": "",           // e.g. "Net 30 days", "C.O.D."
     *     "InclusiveTax": false,      // the "Inclusive?" checkbox
     *     "ARInvoiceDTL": [
     *         {
     *             "AccNo": "5005-2000",   // the "Sales A/C" column
     *             "Description": "",      // line description
     *             "Amount": 1018.49,
     *             "TaxCode": ""           // e.g. "SV-8"
     *         }
     *     ]
     * }
     *
     * Notes
     * -----
     * - Detail array key `ARInvoiceDTL` follows this package's API convention
     *   (cf. ARCNDTL, ARDNDTL, APInvoiceDTL). The newer AOTG public-v1 API
     *   documented on the AutoCount wiki uses `DetailsLine` + `LineState`
     *   instead; that is a different API generation and does NOT apply here.
     * - `$data` is a single document; it is wrapped into the array the API
     *   expects, mirroring createInvoice().
     */
    public function createARInvoice($data)
    {
        $api = $this->callApi(
            uri: 'ARInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get AR Invoices
     */
    public function getARInvoices($numbers = null)
    {
        try {
            $api = $this->callApi(
                uri: 'ARInvoice/GetARInvoice',
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
     * Update AR Invoice
     *
     * - payload structure refer to createARInvoice
     */
    public function updateARInvoice($data)
    {
        $api = $this->callApi(
            uri: 'ARInvoice/UpdateARInvoice',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete AR Invoices
     *
     * - endpoint inferred from sibling resources (ARCreditNote, APInvoice);
     *   not present in the Postman collection but follows the same convention.
     */
    public function deleteARInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'ARInvoice/DeleteARInvoice',
            method: 'POST',
            data: ['DocNo' => array_filter((array) $numbers)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Cancel AR Invoices
     *
     * - endpoint inferred from sibling resources (ARCreditNote, APInvoice);
     *   not present in the Postman collection but follows the same convention.
     */
    public function cancelARInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'ARInvoice/CancelARInvoice',
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
