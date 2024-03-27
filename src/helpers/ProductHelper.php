<?php

namespace digitalpulsebe\craftmultitranslator\helpers;

use Craft;
use craft\commerce\elements\db\ProductQuery;
use craft\commerce\elements\Product;

class ProductHelper
{

    /**
     * @param int|array $elementIds
     * @param int $siteId
     * @return ProductQuery
     */
    public static function query($elementIds, int $siteId): ProductQuery
    {
        return Product::find()->status(null)->id($elementIds)->siteId($siteId);
    }

    /**
     * @param int $elementId
     * @param int $siteId
     * @return Product
     */
    public static function one(int $elementId, int $siteId): ?Product
    {
        return self::query($elementId, $siteId)->one();
    }

    /**
     * @param array $elementIds the element ids to select
     * @param int $siteId the siteId 
     * @return Product[]
     */
    public static function all(array $elementIds, int $siteId): array
    {
        return self::query($elementIds, $siteId)->all();
    }
}