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
use craft\elements\Entry;
use craft\helpers\Json;
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
     * Updated inventory for composite products which means going right around
     * the houses...
     *
     * @param Entry $entry
     *
     * @throws IdentityProviderException
     */
    public function updateInventoryForCompositeProductEntry(Entry $entry)
    {
        $api = Vend::$plugin->api;
        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();

        // Get the product ID
        $productId = $entry->vendProductId;
        if (!$productId) {
            return;
        }

        // Make API call to v1 product endpoint so we can get the composite
        // product IDs off it
        $response = $api->getResponse("products/{$productId}");

        // Check we got back the right data
        if (!$compositeProductData = $response['products'][0]) {
            return;
        }
        if (!$compositeProductData['composites']) {
            return;
        }

        // Track max stock available for this bundle
        $maxStock = 0;

        // Loop the products that make up this composite bundle
        foreach ($compositeProductData['composites'] as $composite) {

            // Make normal inventory API call
            $response = $api->getResponse("2.0/products/{$composite['id']}/inventory", ['page_size' => 500]);

            // Check if we got nothing back and bail
            if (!$response['data']) {
                return;
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
                return;
            }

            // Update the root Vend Product Entry if we can
            $query = Entry::find();
            $criteria = [
                'section' => 'vendProducts',
                'vendProductId' => $composite['id']
            ];
            Craft::configure($query, $criteria);
            $compositeEntry = $query->one();
            if ($compositeEntry) {
                try {
                    $compositeEntry->setFieldValue('vendInventoryCount', $inventoryAmount);
                    if (!Craft::$app->getElements()->saveElement($compositeEntry)) {
                        Craft::error(
                            'Error updating inventory inline during import for entry ID: '.$compositeEntry->id,
                            __METHOD__
                        );
                        Craft::info($entry->getErrors(), __METHOD__);
                    }
                } catch (\Throwable $e) {
                    Craft::error(
                        'Exception thrown whilst updating inventory inline during import for entry ID: '.$compositeEntry->id,
                        __METHOD__
                    );
                }
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

        Craft::dd($maxStock);

        // Update the bundle stock level and store the composite data on the
        // entry so we can use that when the inventory for a product that is in
        // the bundle changes.
        try {
            $entry->setFieldValue('vendInventoryCount', $maxStock);
            $entry->setFieldValue('vendProductComposites', Json::encode($compositeProductData['composites']));
            if (!Craft::$app->getElements()->saveElement($entry)) {
                Craft::error(
                    'Error updating inventory inline during import for entry ID: '.$entry->id,
                    __METHOD__
                );
                Craft::info($entry->getErrors(), __METHOD__);
            }
        } catch (\Throwable $e) {
            Craft::error(
                'Exception thrown whilst updating inventory inline during import for entry ID: '.$entry->id,
                __METHOD__
            );
        }
    }
}