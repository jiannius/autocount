<?php

namespace Jiannius\Autocount\Traits;

trait TaxEntity
{
    /**
     * Create tax entity
     *
     * Payload structure
     * -----------------
     * {
     *     "Name": "WWI Sdn Bhd",
     *     "IdentityType": "MyKAD",             // Required when TaxClassification = 0 (Individual)
     *     "IdentityNo": "123459",
     *     "TIN": "C2560474409000",
     *     "TaxBranchID": "",
     *     "Address": "Address 1",
     *     "PostCode": "12345",
     *     "Phone": "0712356789",
     *     "EmailAddress": "info@wwi.com",
     *     "TaxClassification": 1,              // 0 - Individual, 1 - Business, 2 - Government
     *     "GSTRegisterNo": "",
     *     "SSTRegisterNo": "",
     *     "TradeName": "WWI",
     *     "TourismTaxRegisterNo": "",
     *     "BusinessActivityDesc": "Growing of oil seeds",
     *     "MSICCode": "01113",
     *     "City": "Kuala Lumpur",
     *     "StateCode": "10",
     *     "CountryCode": "MYS"
     * }
     *
     * Notes
     * -----
     * - Field names are case-sensitive.
     * - Either TIN or TaxEntityID can be used as the master key in subsequent
     *   get/update/delete calls. If duplicate TINs exist, use TaxEntityID.
     */
    public function createTaxEntity($data)
    {
        $api = $this->callApi(
            uri: 'TaxEntity',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get tax entities
     *
     * - Lookup by TIN or TaxEntityID. Pass either (or both); empties are filtered out.
     */
    public function getTaxEntities($tins = null, $ids = null)
    {
        try {
            $api = $this->callApi(
                uri: 'TaxEntity/GetTaxEntity',
                method: 'POST',
                data: array_filter([
                    'TIN' => array_filter((array) $tins),
                    'TaxEntityID' => array_filter((array) $ids),
                ]),
            );

            return $api->json();
        }
        catch (\Exception $e) {
            if ($this->isRecordNotFound($e)) return [];
            else throw new \Exception($e->getMessage());
        }
    }

    /**
     * Update tax entity
     *
     * - Payload structure refer to create tax entity.
     * - Lookup by TIN; if duplicate TINs exist, include TaxEntityID in the payload instead.
     */
    public function updateTaxEntity($data)
    {
        $api = $this->callApi(
            uri: 'TaxEntity/UpdateTaxEntity',
            method: 'POST',
            data: [$data],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete tax entities
     *
     * - Lookup by TIN or TaxEntityID. If duplicate TINs exist, use TaxEntityID.
     */
    public function deleteTaxEntities($tins = null, $ids = null)
    {
        $api = $this->callApi(
            uri: 'TaxEntity/DeleteTaxEntity',
            method: 'POST',
            data: array_filter([
                'TIN' => array_filter((array) $tins),
                'TaxEntityID' => array_filter((array) $ids),
            ]),
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }
}
