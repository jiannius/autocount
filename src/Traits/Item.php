<?php

namespace Jiannius\Autocount\Traits;

trait Item
{
    /**
     * Create item
     * 
     * Payload structure
     * -----------------
     *{
     *    "BaseUOM": "UNIT",
     *    "Description": "ITEM-5203 ",
     *    "ItemCode": "003",
     *    "Desc2": "",
     *    "FurtherDescription": "",
     *    // "ItemGroup": "",
     *    // "ItemType": "",
     *    // "ItemBrand": "",
     *    // "ItemClass": "",
     *    // //"TaxCode": "",
     *    // "PurchaseTaxCode": "",
     *    // "HasBatchNo": "F",
     *    // "IsActive": "T",
     *    "ItemDTL": [
     *        {
     *            "UOM": "UNIT",
     *            "Rate": 1.00000000,
     *            "Price": 150.00000000,
     *            "Price2": 0.0,
     *            "Price3": 0.0,
     *            "Price4": 0.0,
     *            "Price5": 0.0,
     *            "Price6": 0.0,
     *            "MinSalePrice": -1.00000000,
     *            "MaxSalePrice": -1.00000000,
     *            "MinPurchasePrice": -1.00000000,
     *            "MaxPurchasePrice": -1.00000000,
     *            "MinQty": 250.00000000,
     *            "MaxQty": 950.00000000,
     *            "NormalLevel": 725.00000000,
     *            "ReOLevel": 300.00000000,
     *            "ReOQty": 500.00000000,
     *            "Cost": 50.00000000,
     *            "RedeemBonusPoint": 0.0,
     *            "BonusPointQty": 0.0,
     *            "BonusPoint": 0.0,
     *            "TotalBalQty": -112.00000000,
     *            "RealCost": 0.0,
     *            "MostRecentlyCost": 0.0,
     *            "FOCLevel": 0.0,
     *            "FOCQty": 0.0,
     *            "Weight": 0.0,
     *            "Volume": 0.0,
     *            "CSGNQty": 0.0,
     *            "MarkupRatio": 0.0,
     *            "MarkdownRatio2": 0.0,
     *            "MarkdownRatio3": 0.0,
     *            "MarkdownRatio4": 0.0,
     *            "MarkdownRatio5": 0.0,
     *            "MarkdownRatio6": 0.0,
     *            "MarkdownRatioMinPrice": 0.0,
     *            "MarkdownRatioMaxPrice": 0.0
     *            //"UDF_Airline":"Value"
     *        }
     *    ]
     *}
    */
    public function createItem($data)
    {
        $api = $this->callApi(
            uri: 'Item',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Get items
     */
    public function getItems($code = null)
    {
        $api = $this->callApi(
            uri: 'V2/Item/GetItem',
            method: 'POST',
            data: ['ItemCode' => array_filter((array) $code)],
        );

        return $api->json();
    }

    /**
     * Update Item
     */
    public function updateItem($data)
    {
        $api = $this->callApi(
            uri: 'Item/UpdateItem',
            method: 'POST',
            data: $data,
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }

    /**
     * Delete multiple items
     */
    public function deleteItems($codes)
    {
        $api = $this->callApi(
            uri: 'Item/DeleteItem',
            method: 'POST',
            data: ['ItemCode' => array_filter((array) $codes)],
        );

        $result = $api->json();

        throw_if(data_get($result, 'Status') === 'Fail', \Exception::class, data_get($result, 'Message'));

        return $result;
    }
}