<?php

namespace Jiannius\Autocount;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class Autocount
{
    public $settings = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->settings = [
            'base_url' => env('AUTOCOUNT_BASE_URL'),
            'user_id' => env('AUTOCOUNT_USER_ID'),
            'password' => env('AUTOCOUNT_PASSWORD'),
            'company' => env('AUTOCOUNT_COMPANY'),
            'company_token' => null,
            'jwt_token' => null,
            'failed_callback' => null,
        ];
    }

    /**
     * Set the base URL
     */
    public function setBaseUrl($value)
    {
        $this->settings['base_url'] = $value;
        return $this;
    }

    /**
     * Set the user ID
     */
    public function setUserId($value)
    {
        $this->settings['user_id'] = $value;
        return $this;
    }

    /**
     * Set the password
     */
    public function setPassword($value)
    {
        $this->settings['password'] = $value;
        return $this;
    }

    /**
     * Set the company
     */
    public function setCompany($value)
    {
        $this->settings['company'] = $value;
        return $this;
    }

    /**
     * Set the failed callback
     */
    public function setFailedCallback($value)
    {
        $this->settings['failed_callback'] = $value;
        return $this;
    }

    /**
     * Get the settings
     */
    public function getSettings($key = null)
    {
        return $key ? data_get($this->settings, $key) : $this->settings;
    }

    /**
     * Get the endpoint
     */
    public function getEndpoint($uri)
    {
        throw_if(!$this->getSettings('base_url'), \Exception::class, 'Missing Autocount Base URL');

        $tail = '/api/';
        $base = str($this->getSettings('base_url').$tail)->finish($tail);

        return $base.$uri;
    }

    /**
     * Get the cache key
     */
    public function getCacheKey()
    {
        return 'autocount_jwt_token_'.(string) str($this->getSettings('company'))->slug();
    }

    /**
     * Get the company token
     * 
     * note: anyone can get company token, this token needs to used in the login API together with the user id and password to get the jwt token
     */
    public function getCompanyToken()
    {
        throw_if(!$this->getSettings('company'), \Exception::class, 'Missing Autocount Company Name');

        $url = $this->getEndpoint('MultiCompany');
        $response = Http::get($url)->throw();
        $companies = $response->json();
        $company = collect($companies)->firstWhere('CompanyName', $this->getSettings('company'));

        return data_get($company, 'ToKen');
    }

    /**
     * Get the jwt token
     */
    public function getJwtToken()
    {
        $cachekey = $this->getCacheKey();
        $cache = Cache::get($cachekey);
        $jwtToken = data_get($cache, 'JWTToken');
        $expiry = data_get($cache, 'expired_at');

        if ($jwtToken && $expiry?->isFuture()) return $jwtToken;

        Cache::forget($cachekey);

        $userId = $this->getSettings('user_id');
        $password = $this->getSettings('password');
        $companyToken = $this->getCompanyToken();

        throw_if(!$userId || !$password, \Exception::class, 'Missing Autocount User ID / Password');
        throw_if(!$companyToken, \Exception::class, 'Unable to get Autocount Company Token');

        $url = $this->getEndpoint('v3/Login');

        $data = [
            'UserID' => $userId,
            'Password' => $password,
            'Token' => $companyToken,
        ];

        $response = Http::post(url: $url, data: $data)->throw();

        $isSuccessfulLogin = data_get($response->json(), '0.LoginToAutoCount');

        throw_if(!$isSuccessfulLogin, \Exception::class, 'Autocount Login Failed');

        Cache::put($cachekey, [
            ...data_get($response->json(), '0'),
            'expired_at' => now()->addDays(6),
        ]);

        return $this->getJwtToken();
    }

    /**
     * Test connection
     */
    public function testConnection()
    {
        if ($this->getJwtToken()) {
            Cache::forget($this->getCacheKey());
            return true;
        };

        return false;
    }

    /**
     * Call the API
     */
    public function callApi($uri, $method = 'GET', $data = []) : mixed
    {
        $method = strtolower($method);
        $jwtToken = $this->getJwtToken();

        if (!$jwtToken) abort(500, 'Missing Autocount JWT Token');

        $url = $this->getEndpoint($uri);
        $result = Http::withHeader('Authorization', $jwtToken)->$method($url, $data);

        // system level fail
        if ($result->failed()) {
            if ($callback = $this->getSettings('failed_callback')) $result = $callback($result);
            else $result->throw();
        }

        // response level fail
        $status = data_get($result->json(), 'Status');
        $message = data_get($result->json(), 'Message');
        $isFailed = $status === 'Fail' && !str($message)->is('*Record Not Found*');
        throw_if($isFailed, \Exception::class, $message);

        return $result;
    }

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
    public function createInvoices($data)
    {
        $api = $this->callApi(
            uri: 'Invoice',
            method: 'POST',
            data: Arr::map($data, fn ($invoice) =>[
                'DocStatus' => 'A',
                'SubmitEInvoice' => 'T',
                'ConsolidatedEInvoice' => 'F',
                ...Arr::except($invoice, 'IVDTL'),
                'IVDTL' => Arr::map(data_get($invoice, 'IVDTL', []), fn ($item) => [
                    'Classification' => '022',
                    ...$item,
                ]),
            ]),
        );

        return data_get($api->json(), 'ResultTable');
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

        return data_get($api->json(), 'ResultTable');
    }

    /**
     * Update multiple invoices
     * 
     * - payload structure refer to create invoice
     */
    public function updateInvoices($data)
    {
        $api = $this->callApi(
            uri: 'Invoice/UpdateInvoice',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete multiple invoices
     */
    public function deleteInvoices($numbers)
    {
        $api = $this->callApi(
            uri: 'Invoice/DeleteInvoice',
            method: 'POST',
            data: [
                'DocNo' => $numbers,
            ],
        );

        return $api->json();
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
                'DocNo' => $numbers,
                'EinvoiceCancelReason' => $reason,
            ],
        );

        return $api->json();
    }

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

    /**
     * Create multiple credit notes
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
    public function createCreditNotes($data)
    {
        $api = $this->callApi(
            uri: 'CreditNote',
            method: 'POST',
            data: Arr::map($data, fn ($creditNote) => [
                'DocStatus' => 'A',
                'SubmitEInvoice' => 'T',
                'ConsolidatedEInvoice' => 'F',
                ...Arr::except($creditNote, 'CNDTL'),
                'CNDTL' => Arr::map(data_get($creditNote, 'CNDTL', []), fn ($item) => [
                    'Classification' => '022',
                    ...$item,
                ]),
            ]),
        );

        return data_get($api->json(), 'ResultTable');
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

        return data_get($api->json(), 'ResultTable');
    }

    /**
     * Update multiple credit notes
     * 
     * - payload structure refer to create credit notes
     */
    public function updateCreditNotes($data)
    {
        $api = $this->callApi(
            uri: 'CreditNote/UpdateCreditNote',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete multiple credit notes
     */
    public function deleteCreditNotes($numbers)
    {
        $api = $this->callApi(
            uri: 'CreditNote/DeleteCreditNote',
            method: 'POST',
            data: [
                'DocNo' => $numbers,
            ],
        );

        return $api->json();
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
                'DocNo' => $numbers,
                'EinvoiceCancelReason' => $reason,
            ],
        );
    }

    /**
     * Create multiple debit notes
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
    public function createDebitNotes($data)
    {
        $api = $this->callApi(
            uri: 'DebitNote',
            method: 'POST',
            data: Arr::map($data, fn ($debitNote) => [
                'DocStatus' => 'A',
                'SubmitEInvoice' => 'T',
                'ConsolidatedEInvoice' => 'F',
                ...$debitNote,
            ]),
        );

        return data_get($api->json(), 'ResultTable');
    }

    /**
     * Get debit notes
     */
    public function getDebitNotes($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'DebitNote/GetDebitNote',
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
     * Update multiple debit notes
     * 
     * - payload structure refer to create debit notes
     */
    public function updateDebitNotes($data)
    {
        $api = $this->callApi(
            uri: 'DebitNote/UpdateDebitNote',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete multiple debit notes
     */
    public function deleteDebitNotes($numbers)
    {
        $api = $this->callApi(
            uri: 'DebitNote/DeleteDebitNote',
            method: 'POST',
            data: [
                'DocNo' => $numbers,
            ],
        );

        return $api->json();
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
                'DocNo' => $numbers,
                'EinvoiceCancelReason' => $reason,
            ],
        );

        return $api->json();
    }

    /**
     * Create debtor
     * 
     * Payload structure
     * -----------------
     * {
     *     "DebtorType": "G01-A",
     *     "CompanyName": "WWI",
     *     "CurrencyCode": "SGD",
     *     "Decs2": "",
     *     "RegisterNo": "360",
     *     "ControlAccount": "300-0000",
     *     "AccNo": "",
     *     "IsGroupCompany": "F",           // T - true, F - false
     *     "IsActive": "T",                 // T - true, F - false
     *     "IsCashSaleDebtor": "F",         // T - true, F - false
     *     "TaxEntityID": "1",              // TIN number
     *     "Address1": "Address 1",
     *     "Address2": "Address 2",
     *     "Address3": "Address 3",
     *     "Address4": "Address 4",
     *     "PostCode": "123456",
     *     "Phone1": "071235679",
     *     "Phone2": "",
     *     "Mobile": "",
     *     "Fax1": "",
     *     "Fax2": "",
     *     "AreaCode":"",
     *     "DeliverAddr1": "123",
     *     "DeliverAddr2": "",
     *     "DeliverAddr3": "",
     *     "DeliverAddr4": "",
     *     "DeliverPostCode": "",
     *     "EmailAddress": "",
     *     "WebURL": "",
     *     "Attention": "",
     *     "NatureOfBusiness": "",
     *     "SalesAgent":"",
     *     "StatementType": "O",            // (O) Open Item, (B) Balance forward, (N) None statement
     *     "AgoingOn": "I"                  // (I) Invoice date, (D) Due date
     *     "DisplayTerm":"C.O.D"
     * }
     */
    public function createDebtor($data)
    {
        $api = $this->callApi(
            uri: 'Debtor',
            method: 'POST',
            data: [
                'IsGroupCompany' => 'F',
                'IsActive' => 'T',
                'IsCashSaleDebtor' => 'F',
                'StatementType' => 'O',
                'AgoingOn' => 'I',
                ...$data,
            ],
        );

        return data_get($api->json(), '0');
    }

    /**
     * Get multiple debtor
     */
    public function getDebtors($codes)
    {
        $api = $this->callApi(
            uri: 'Debtor/GetDebtor',
            method: 'POST',
            data: [
                'AccNo' => (array) $codes,
            ],
        );

        return $api->json();
    }

    /**
     * Update debtor
     * 
     * Payload structure
     * -----------------
     * [
     *     {
     *         "AccNo": "300-A001",
     *         "CompanyName" : "Client 456",
     *         "Address1" : "Address A",
     *         "Address2" : "Address B",
     *         "Address3" : "Address C",
     *         "Address4" : "Address D",
     *         "Attention" : "Jack",
     *         "Phone1" : "0123451456",
     *         "EmailAddress": "client@gmail.com"
     *     }
     * ]
     */
    public function updateDebtor($data)
    {
        $api = $this->callApi(
            uri: 'Debtor/UpdateDebtor',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete debtor
     */
    public function deleteDebtor($code)
    {
        $api = $this->callApi(
            uri: 'Debtor/DeleteDebtor',
            method: 'POST',
            data: [
                'AccNo' => $code,
            ],
        );

        return $api->json();
    }

    /**
     * Create creditor
     * 
     * Payload structure
     * -----------------
     * {
     *     "CreditorType": "",
     *     "CompanyName" : "WWI",
     *     "Decs2":"",
     *     "RegisterNo": "",
     *     "ControlAccount":"300-0000",
     *     "AccNo":"300-G001",
     *     "IsGroupCompany": "F",               // T - true, F - false
     *     "IsActive": "T",                     // T - true, F - false
     *     "TaxEntityID":"1",                   // TIN number
     *     "Address1" : "Address 1",
     *     "Address2" : "Address 2",
     *     "Address3" : "Address 3",
     *     "Address4" : "Address 4",
     *     "PostCode":"123456",
     *     "Phone1":"071235679",
     *     "Phone2":"",
     *     "Mobile":"",
     *     "Fax1":"",
     *     "Fax2":"",
     *     "EmailAddress":"",
     *     "WebURL":"",
     *     "Attention":"",
     *     "NatureOfBusiness":"",
     *     "SalesAgent":"",
     *     "CurrencyCode":"",
     *     "StatementType":"O",                 // (O) Open Item, (B) Balance forward, (N) None statement
     *     "AgoingOn":"I"                       // (I) Invoice date, (D) Due date
     *     "DisplayTerm":"C.O.D"
     * }
     */
    public function createCreditor($data)
    {
        $api = $this->callApi(
            uri: 'Creditor',
            method: 'POST',
            data: [
                'IsGroupCompany' => 'F',
                'IsActive' => 'T',
                'StatementType' => 'O',
                'AgoingOn' => 'I',
                ...$data,
            ],
        );

        return $api->json();
    }

    /**
     * Get multiple creditor
     */
    public function getCreditors($codes)
    {
        $api = $this->callApi(
            uri: 'Creditor/GetCreditor',
            method: 'POST',
            data: [
                'AccNo' => $codes,
            ],
        );

        return $api->json();
    }

    /**
     * Update creditor
     * 
     * Payload structure
     * -----------------
     * [
     *     {
     *         "AccNo": "300-C001",
     *         "CompanyName" : "Client 456",
     *         "Address1" : "Address A",
     *         "Address2" : "Address B",
     *         "Address3" : "Address C",
     *         "Address4" : "Address D",
     *         "Attention" : "Jack",
     *         "Phone1" : "0123451456",
     *         "EmailAddress": "client@gmail.com"
     *     }
     * ]
     */
    public function updateCreditor($data)
    {
        $api = $this->callApi(
            uri: 'Creditor/UpdateCreditor',
            method: 'POST',
            data: $data,
        );

        return $api->json();
    }

    /**
     * Delete creditor
     */
    public function deleteCreditor($code)
    {
        $api = $this->callApi(
            uri: 'Creditor/DeleteCreditor',
            method: 'POST',
            data: [
                'AccNo' => $code,
            ],
        );

        return $api->json();
    }

    /**
     * Create payment
     * 
     * Payload structure
     * -----------------
     * [
     *  "DebtorCode": "300-A001",
     *  "Description": "", 
     *  "ProjNo": "GOTGVOL03",
     *  "DeptNo": "D001",
     *  "ARPaymentDTL": [
     *      {
     *          "PaymentMethod": "CASH",
     *          "PaymentAmt": 100,
     *          "PaymentBy": "",
     *          "ChequeNo":"",
     *          "FloatDay": 0,
     *          "BankCharge": 0.00,
     *          "IsRCHQ": "F",
     *          "RCHQDate": ""
     *      }
     *  ],
     *  "ARPaymentKnockOff": [
     *      {
     *          "DocNo": "I-000004",
     *          "KnockOffAmount": 145 ,//Can not be PaymentAmt > Outstanding 
     *          "KnockOffDocType": "RI"
     *          // RI = INVOICE
     *          // RD = DEBIT NOTE
     *      }
     *  ]
     * ]
     */
    public function createPayment($data)
    {
        $api = $this->callApi(
            uri: 'ARPayment',
            method: 'POST',
            data: $data,
        );

        return data_get($api->json(), 'ResultTable.0');
    }

    /**
     * Get invoices
     * 
     * - date format - YYYY/MM/DD
     */
    public function getPayments($numbers = null, $from = null, $to = null)
    {
        $api = $this->callApi(
            uri: 'ARPayment/GetARPayment',
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
     * Update payment
     * 
     * - payload structure refer to create payment
     */
    public function updatePayment($data)
    {
        $api = $this->callApi(
            uri: 'ARPayment/UpdateARPayment',
            method: 'POST',
            data: $data,
        );

        return data_get($api->json(), 'ResultTable.0');
    }

    /**
     * Delete multiple payments
     */
    public function deletePayments($numbers)
    {
        $api = $this->callApi(
            uri: 'ARPayment/DeleteARPayment',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
            ],
        );

        return data_get($api->json(), 'ResultTable');
    }

    /**
     * Cancel multiple payments
     */
    public function cancelPayments($numbers)
    {
        $api = $this->callApi(
            uri: 'ARPayment/CancelARPayment',
            method: 'POST',
            data: [
                'DocNo' => array_filter((array) $numbers),
                'Cancelled' => true,
            ],
        );

        return data_get($api->json(), 'ResultTable');
    }
}
