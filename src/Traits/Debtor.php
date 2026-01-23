<?php

namespace Jiannius\Autocount\Traits;

trait Debtor
{
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

        $debtor = data_get($api->json(), '0');

        throw_if(data_get($debtor, 'Status') === 'Fail', \Exception::class, data_get($debtor, 'Message'));

        return $debtor;
    }

    /**
     * Get multiple debtor
     */
    public function getDebtors($codes)
    {
        $api = $this->callApi(
            uri: 'Debtor/GetDebtor',
            method: 'POST',
            data: ['AccNo' => (array) $codes],
        );

        return $api->json();
    }

    /**
     * Update debtor
     * 
     * Payload structure
     * -----------------
     * {
     *     "AccNo": "300-A001",
     *     "CompanyName" : "Client 456",
     *     "Address1" : "Address A",
     *     "Address2" : "Address B",
     *     "Address3" : "Address C",
     *     "Address4" : "Address D",
     *     "Attention" : "Jack",
     *     "Phone1" : "0123451456",
     *     "EmailAddress": "client@gmail.com"
     * }
     */
    public function updateDebtor($data)
    {
        $api = $this->callApi(
            uri: 'Debtor/UpdateDebtor',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        return data_get($result, '0');
    }

    /**
     * Delete debtor
     */
    public function deleteDebtor($code)
    {
        $api = $this->callApi(
            uri: 'Debtor/DeleteDebtor',
            method: 'POST',
            data: ['AccNo' => $code],
        );

        return $api->json();
    }   
}