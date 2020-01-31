<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\controllers;

use angellco\vend\models\Settings;
use angellco\vend\Vend;
use Craft;
use craft\db\Paginator;
use craft\elements\Entry;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\twig\variables\Paginate;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use yii\web\Response;

/**
 * Products controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ProductsController extends Controller
{
    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = true;

    // Public Methods
    // =========================================================================

    /**
     * Fetches a list of products from the Vend API.
     *
     * @return Response
     * @throws IdentityProviderException
     */
    public function actionList(): Response
    {
        $api = Vend::$plugin->api;
        $request = Craft::$app->getRequest();
        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();

        // Set the page size
        $params = [
            'page_size' => 500,
            'deleted' => false
        ];

        // Set the after param which will be the max version number in the
        // previous collection
        $after = $request->getQueryParam('after');
        if ($after) {
            $params['after'] = $after;
        }

        // Fetch the products
        $response = $api->getResponse('2.0/products', $params);

        // Check if we got nothing back and bail
        if (!$response['data']) {
            return $this->asJson([
                'products' => [],
                'nextUrl' => null
            ]);
        }

        // Format our result data
        $products = [];

        $excludedProductIds = [$settings->vend_discountProductId];
        foreach ($settings->shippingMap['rules'] as $rule) {
            $excludedProductIds[] = $rule['productId'];
        }

        foreach ($response['data'] as $product) {
            if ($product['id'] && !in_array($product['id'], $excludedProductIds, true)) {
                $products[] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'productTypeId' => $product['product_type_id'],
                    'brandId' => $product['brand_id'],
                    'supplierId' => $product['supplier_id'],
                    'tagIds' => is_array($product['tag_ids']) ? implode(',', $product['tag_ids']) : $product['tag_ids'],
                    'hasVariants' => (bool)$product['has_variants'],
                    'isVariant' => (bool)$product['variant_parent_id'],
                    'variantParentId' => $product['variant_parent_id'],
                    'variantName' => $product['variant_name'],
                    'productJson' => Json::encode($product)
                ];
            }
        }

        // Make our response array
        $return = [
            'products' => $products
        ];

        // Sort out the next URL
        $nextUrl = UrlHelper::actionUrl('vend/products/list', [
            'after' => $response['version']['max']
        ]);
        $return['nextUrl'] = $nextUrl;

        return $this->asJson($return);
    }

    /**
     * Paginated list of product entries from the `vendProducts` section.
     *
     * This is intended to be consumed by Feed Me for importing into Commerce
     * as products with variants. The format includes a default variant set to
     * the main product with a nested array of variants if there are any.
     *
     * Variant objects include the full vend product object as received from the
     * Vend API.
     *
     * @return Response
     */
    public function actionImport(): Response
    {
        $request = Craft::$app->getRequest();
        $profiles = Vend::$plugin->importProfiles;

        // Set up the basic query
        $query = Entry::find();
        $criteria = [
            'limit' => null,
            'section' => 'vendProducts',
            // Exclude variants
            'vendProductIsVariant' => 'not 1'
        ];
        Craft::configure($query, $criteria);

        // Fetch the profile if there is one
        $profileHandle = (string) $request->getQueryParam('profile');
        $profile = $profiles->getByHandle($profileHandle);

        // Apply the criteria from it
        if ($profile) {
            $profile->apply($query);
        }

        // Set up the paginator
        $paginator = new Paginator($query, [
            'pageSize' => 100,
            'currentPage' => $request->pageNum
        ]);

        $twigPaginate = Paginate::create($paginator);

        // Loop over the results and create an array of just the things we want
        $products = [];

        /** @var Entry $rawProduct */
        foreach ($paginator->getPageResults() as $rawProduct) {

            $variants = [];

            // Add the default variant
            $variants[] = $this->_rawProductEntryToVariantArray($rawProduct, true);

            // If this product has additional variants, then fetch them using
            // the `vendProductVariantParentId` field
            if ($rawProduct->vendProductHasVariants) {
                $variantQuery = Entry::find();
                $variantCriteria = [
                    'limit' => null,
                    'section' => 'vendProducts',
                    'vendProductVariantParentId' => $rawProduct->vendProductId,
                    'vendProductIsVariant' => '1'
                ];
                Craft::configure($variantQuery, $variantCriteria);

                /** @var Entry $rawVariant */
                foreach ($variantQuery->all() as $rawVariant) {
                    $variants[] = $this->_rawProductEntryToVariantArray($rawVariant);
                }
            }

            $products[] = [
                'id' => $rawProduct->vendProductId,
                'name' => $rawProduct->title,
                'variants' => $variants
            ];
        }

        // Make the object we want Feed Me to consume
        $return = [
            'products' => $products,
            'nextUrl' => $twigPaginate->getNextUrl()
        ];

        return $this->asJson($return);
    }

    /**
     * Fetches inventory from the Vend API.
     *
     * @return Response
     * @throws IdentityProviderException
     */
    public function actionInventory(): Response
    {
        $api = Vend::$plugin->api;
        $request = Craft::$app->getRequest();
        $settings = Vend::$plugin->getSettings();

        // Set the page size
        $params = [
            'page_size' => 5000
        ];

        // Set the after param which will be the max version number in the
        // previous collection
        $after = $request->getQueryParam('after');
        if ($after) {
            $params['after'] = $after;
        }

        // Fetch the products
        $response = $api->getResponse('2.0/inventory', $params);

        // Check if we got nothing back and bail
        if (!$response['data']) {
            return $this->asJson([
                'products' => [],
                'nextUrl' => null
            ]);
        }

        // Prep the product array by stripping out records that don’t match
        // our selected outlet
        $products = [];
        foreach ($response['data'] as $product) {
            if ((string)$settings->vend_outletId === (string)$product['outlet_id']) {
                $products[] = $product;
            }
        }

        // Make the object we want Feed Me to consume
        $return = [
            'products' => $products,
        ];

        // Sort out the next URL
        $nextUrl = UrlHelper::actionUrl('vend/products/inventory', [
            'after' => $response['version']['max']
        ]);
        $return['nextUrl'] = $nextUrl;

        return $this->asJson($return);
    }

    // Private Methods
    // =========================================================================

    /**
     * @param Entry $rawProduct
     * @param bool  $default
     *
     * @return array
     */
    private function _rawProductEntryToVariantArray(Entry $rawProduct, $default = false): array
    {
        $productJson = Json::decode($rawProduct->vendProductJson);
        $options = [];
        $formattedOptionNames = [];
        $formattedOptionValues = [];

        foreach ($productJson['variant_options'] as $option) {
            $options[] = [
                'name' => $option['name'],
                'value' => $option['value']
            ];
            $formattedOptionNames[] = $option['name'];
            $formattedOptionValues[] = $option['value'];
        }

        $formattedOptionNames = implode(',', $formattedOptionNames);
        $formattedOptionValues = implode(',', $formattedOptionValues);

        return [
            'id' => $rawProduct->vendProductId,
            'name' => $rawProduct->vendProductVariantName,
            'parentProductId' => $rawProduct->vendProductVariantParentId,
            'default' => $default,
            'inventory' => $rawProduct->vendInventoryCount,
            'formattedOptionNames' => $formattedOptionNames,
            'formattedOptionValues' => $formattedOptionValues,
            'options' => $options,
            'productJson' => $productJson
        ];
    }

}