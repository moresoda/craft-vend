<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\services;

use angellco\vend\models\Settings;
use angellco\vend\Vend;
use Craft;
use craft\base\Component;
use craft\commerce\elements\Variant;
use craft\elements\Entry;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

/**
 * Orders service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.5.0
 */
class Products extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * Returns the child products that make up a composite / bundle product.
     *
     * @param $productId
     *
     * @return bool|mixed
     * @throws IdentityProviderException
     */
    public function getComposites($productId)
    {
        $api = Vend::$plugin->api;

        // Make API call to v1 product endpoint so we can get the composite
        // product IDs off it
        $response = $api->getResponse("products/{$productId}");

        // Check we got back the right data
        if (!$compositeProductData = $response['products'][0]) {
            return false;
        }
        if (!$compositeProductData['composites']) {
            return false;
        }

        return $compositeProductData['composites'];
    }

    /**
     * Calculates the inventory for a composite product.
     *
     * @param $vendProductId
     *
     * @return bool|int|string|null
     * @throws IdentityProviderException
     */
    public function calculateInventoryForComposite($vendProductId)
    {
        $api = Vend::$plugin->api;
        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();

        // Make API call to v1 product endpoint so we can get the composite
        // product IDs off it
        $response = $api->getResponse("products/{$vendProductId}");

        // Check we got back the right data
        if (!$compositeProductData = $response['products'][0]) {
            return false;
        }
        if (!$compositeProductData['composites']) {
            return false;
        }

        // Track max stock available for this bundle
        $maxStock = 0;

        // Loop the products that make up this composite bundle
        foreach ($compositeProductData['composites'] as $composite) {

            // Make normal inventory API call
            $response = $api->getResponse("2.0/products/{$composite['id']}/inventory", ['page_size' => 500]);

            // Check if we got nothing back and bail
            if (!$response['data']) {
                return false;
            }

            // Find the one for our outlet
            $inventoryAmount = null;
            foreach ($response['data'] as $inventoryItem) {
                if ($inventoryItem['outlet_id'] === $settings->vend_outletId) {
                    $inventoryAmount = $inventoryItem['inventory_level'];
                    break;
                }
            }

            // Check we got some inventory
            if ($inventoryAmount === null) {
                return false;
            }

            // Work out the max bundles available for this product / composite
            $maxBundles = bcdiv($inventoryAmount, $composite['count']);

            // Track the lowest max bundle number as the stock level for the
            // whole bundle
            if ($maxBundles > 0) {
                if ($maxStock === 0 || $maxBundles < $maxStock) {
                    $maxStock = $maxBundles;
                }
            }
        }

        return $maxStock;
    }

    /**
     * @param $vendProductId
     * @param $stock
     *
     * @return bool
     */
    public function updateInventoryForEntry($vendProductId, $stock): bool
    {
        $entry = Entry::findOne([
            'vendProductId' => $vendProductId,
            'section' => 'vendProducts',
            'status' => null,
        ]);

        if (!$entry) {
            return false;
        }

        try {
            $entry->setFieldValue('vendInventoryCount', $stock);
            if (!Craft::$app->getElements()->saveElement($entry)) {
                Craft::error(
                'Error updating inventory for entry ID: '.$entry->id,
                __METHOD__
                );
                Craft::info($entry->getErrors(), __METHOD__);
            }

            return true;
        } catch (\Throwable $e) {
            Craft::error(
                'Exception thrown whilst updating inventory for entry ID: '.$entry->id,
                __METHOD__
            );
        }

        return false;
    }

    /**
     * @param $vendProductId
     * @param $stock
     *
     * @return bool
     */
    public function updateInventoryForVariant($vendProductId, $stock): bool
    {
        $variant = Variant::findOne([
            'status' => null,
            'vendProductId' => $vendProductId
        ]);

        if (!$variant) {
            return false;
        }

        try {
            $variant->stock = $stock;
            if (!Craft::$app->getElements()->saveElement($variant)) {
                Craft::error(
                    'Error updating inventory for variant ID: '.$variant->id,
                    __METHOD__
                );
                Craft::info($variant->getErrors(), __METHOD__);
            }

            return true;
        } catch (\Throwable $e) {
            Craft::error(
                'Exception thrown whilst updating inventory for variant ID: '.$variant->id,
                __METHOD__
            );
        }

        return false;
    }
}