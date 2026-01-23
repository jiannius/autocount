<?php

namespace Jiannius\Autocount\Traits;

trait Creditor
{
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
}