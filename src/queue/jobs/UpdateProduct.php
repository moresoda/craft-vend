<?php

namespace angellco\vend\queue\jobs;

use angellco\vend\errors\ProductUpdatesLockedException;
use angellco\vend\services\Api;
use angellco\vend\Vend;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\errors\ElementNotFoundException;
use craft\helpers\ArrayHelper;
use craft\queue\BaseJob;
use DateTime;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Throwable;
use yii\base\Exception;
use yii\queue\RetryableJobInterface;

final class UpdateProduct extends BaseJob implements RetryableJobInterface
{
    const PRODUCT_TYPES = [
        // General
        'b8ca3a65-011c-11e4-f728-e521433e9a5b' => 2,
        // Yarn
        'b8ca3a65-017a-11e4-f728-ec4d8bacb3d3' => null,
        // Fabric
        'b8ca3a65-017a-11e4-f728-ec4d8e84d13a' => 3,
        // Zips
        'b8ca3a65-017a-11e4-f728-ec4d8d105be7' => 2,
        // Liberty Products
        'b8ca3a65-017a-11e4-f728-ec4dafb67222' => 2,
        // Knit Haberdashery
        'b8ca3a65-017a-11e4-f728-ec4d8d98f51a' => 2,
        // Magazines
        'b8ca3a65-017a-11e4-f728-ec525a9d2bd4' => 2,
        // Postage
        'b8ca3a65-017a-11e4-f728-ec54c4d5afd8' => null,
        // Knitting Patterns
        'b8ca3a65-017a-11e4-f728-ec526723f96c' => 2,
        // Buttons
        'b8ca3a65-017a-11e4-f728-ec4d935bb279' => 2,
        // Ribbons and Trims
        'b8ca3a65-017a-11e4-f728-ec4d8c4dc6a7' => 2,
        // Haberdashery
        'b8ca3a65-017a-11e4-f728-ec4d8bc27143' => 2,
        // Sewing Patterns
        'b8ca3a65-017a-11e4-f728-ec4da5500679' => 4,
        // Bag Hardware
        'b8ca3a65-017a-11e4-f728-ec4d8c38201e' => 2,
        // Gift
        'b8ca3a65-017a-11e4-f728-ec4d8efbc2bf' => 2,
        // Books
        'b8ca3a65-017a-11e4-f728-ec4d8ce34197' => 2,
        // Thread
        'b8ca3a65-017a-11e4-f728-ec4dc9261a1a' => 2,
        // float/petty cash
        'b8ca3a65-017a-11e4-f728-ee8066cb0d05' => null,
        // International Postage
        '064dce89-c77a-11e5-ec2a-f0e6c9370aba' => null,
        // Leather
        '064dce89-c77a-11e5-ec2a-f2a2b7713b93' => 2,
        // International Postage
        '0274a24e-fd7a-11e6-f3f1-0c52670a0cf8' => null,
        // Swatch Postage
        '060f02b1-c87a-11e6-fcd2-5012b600f618' => null,
        // Trainer soles
        '707dfef0-a558-7d42-d533-71b8d16e32fd' => 2,
        // Video
        '0242e39e-bf7a-11ea-fc6f-8618ac965691' => 2,
        // Printing
        '0242e39e-bf7a-11ea-fc6f-cb37e73ae0a4' => 6,
        // Digital Sewing Pattern
        'bd0d8442-4c9b-8ffc-1dd6-d02139293cb9' => 4,
        // Sewing Society
        '5285b363-caa9-7795-7dce-aa4156deb5c8' => 2,
        // SS-videos
        '02f6cd34-4c7a-11ec-ecfb-205883bb6622' => 8,
        // SS-kits
        '02f6cd34-4c7a-11ec-ecfb-205886d14ca8' => 7,
        // Test
        '5522763b-8ce5-45a4-8299-8fad9e3d8fa6' => 2,
    ];

    /**
     * @var string Vend Product ID
     */
    public $product_id;

    /**
     * @var Api Vend API
     */
    private $api;

    protected function defaultDescription(): string
    {
        return Craft::t('vend', 'Updating product');
    }

    public function getTtr()
    {
        return 5 * 60; // 5min
    }

    public function canRetry($attempt, $error)
    {
        return ($attempt < 5) && ($error instanceof ProductUpdatesLockedException);
    }

    public function execute($queue): void
    {
        $this->api = Vend::$plugin->api;

        $product_data = $this->getProduct($this->product_id);

        if (!Craft::$app->getMutex()->acquire(md5($this->product_id . $product_data['sku_number']))) {
            throw new ProductUpdatesLockedException();
        }

        if (count($product_data['variants']) === 0) {
            $this->processSimpleProduct($this->product_id, $product_data);
        }

        if (count($product_data['variants']) > 0) {
            $this->processProductWithVariants($this->product_id, $product_data);
        }
    }

    private function processSimpleProduct(string $id, array $data): void
    {
        $inventory = $this->getInventory($id);

        $variant = Variant::find()->vendProductId($id)->one();

        if (!$variant) {
            $product = $this->createProduct($id, $data);

            // Create variant
            $variant = new Variant();
            $variant->isDefault = true;
            $variant->hasUnlimitedStock = !$data['tracks_inventory'];
            $variant->title = $data['name'];
            $variant->sku = $data['sku_number'];
            $variant->stock = $inventory['current_amount'];
            $variant->price = $data['price_standard']['tax_inclusive'];
            $variant->setFieldValue('vendProductId', $id);

            if (count($data['suppliers']) === 1) {
                $variant->setFieldValue(
                    'vendProductSupplierName',
                    $this->getSupplierName($data['suppliers'][0]['id'])
                );
            }

            $product->setVariants([$variant]);

            try {
                if (!Craft::$app->getElements()->saveElement($product)) {
                    throw new \Exception();
                }
            } catch (Throwable $e) {
                Craft::error(
                    "Can not save element 'Product'",
                    __METHOD__
                );
            }
        } else {
            $product = $variant->product;
            $product->title = $data['name'];
            $product->defaultVariant->hasUnlimitedStock = !$data['tracks_inventory'];
            $product->defaultVariant->title = $data['name'];
            $product->defaultVariant->sku = $data['sku_number'];
            $product->defaultVariant->stock = $inventory['current_amount'];
            $product->defaultVariant->price = $data['price_standard']['tax_inclusive'];

            if (count($data['suppliers']) === 1) {
                $product->defaultVariant->setFieldValue(
                    'vendProductSupplierName',
                    $this->getSupplierName($data['suppliers'][0]['id'])
                );
            }

            try {
                if (!Craft::$app->getElements()->saveElement($product)) {
                    throw new \Exception();
                }
            } catch (Throwable $e) {
                Craft::error(
                    "Can not save element 'Product[$product->id]'",
                    __METHOD__
                );
            }
        }
    }

    private function processProductWithVariants(string $id, array $data): void
    {
        $variant = Variant::find()->vendProductId($id)->one();

        if (!$variant) {
            $product = $this->createProduct($id, $data);

            $variantsCount = count($data['variants']);

            $variants = [];

            // Create variants
            for ($i = 0; $i < $variantsCount; $i++) {
                $variantId = $data['variants'][$i]['id'];

                $inventory = $this->getInventory($variantId);

                $variant = new Variant();
                $variant->isDefault = $id === $variantId;
                $variant->title = $data['variants'][$i]['variant_definitions'][0]['value'];
                $variant->sku = $data['variants'][$i]['product_codes'][0]['code'];
                $variant->price = $data['variants'][$i]['price_standard']['tax_inclusive'];
                $variant->hasUnlimitedStock = !$data['tracks_inventory'];
                $variant->stock = $inventory['current_amount'];

                if ($id === $variantId) {
                    $product->setFieldValue('vendProductId', $id);
                    $variant->setFieldValue('vendProductId', $id);
                    $variant->setFieldValue('vendProductVariantParentId', null);
                } else {
                    $variant->setFieldValue('vendProductId', $data['variants'][$i]['id']);
                    $variant->setFieldValue('vendProductVariantParentId', $id);
                }

                if (count($data['suppliers']) === 1) {
                    $variant->setFieldValue(
                        'vendProductSupplierName',
                        $this->getSupplierName($data['suppliers'][0]['id'])
                    );
                }

                $variant->setFieldValue('vendProductVariantLabel', $this->getVariantLabel($data['variants'][$i]['variant_definitions']));

                $variants[] = $variant;
            }

        } else {
            $product = $variant->product;

            $variantsCount = count($data['variants']);

            $variants = [];

            // Create variants
            for ($i = 0; $i < $variantsCount; $i++) {
                $variantId = $data['variants'][$i]['id'];

                $inventory = $this->getInventory($variantId);

                $variant = Variant::find()->vendProductId($variantId)->one();

                if (!$variant) {
                    $variant = new Variant();
                }

                $variant->isDefault = $id === $variantId;
                $variant->title = $data['variants'][$i]['variant_definitions'][0]['value'];
                $variant->sku = $data['variants'][$i]['product_codes'][0]['code'];
                $variant->price = $data['variants'][$i]['price_standard']['tax_inclusive'];
                $variant->hasUnlimitedStock = !$data['tracks_inventory'];
                $variant->stock = $inventory['current_amount'];

                if ($id === $variantId) {
                    $product->setFieldValue('vendProductId', $id);
                    $variant->setFieldValue('vendProductId', $id);
                    $variant->setFieldValue('vendProductVariantParentId', null);
                } else {
                    $variant->setFieldValue('vendProductId', $data['variants'][$i]['id']);
                    $variant->setFieldValue('vendProductVariantParentId', $id);
                }

                if (count($data['suppliers']) === 1) {
                    $variant->setFieldValue(
                        'vendProductSupplierName',
                        $this->getSupplierName($data['suppliers'][0]['id'])
                    );
                }

                $variantLabel = $this->getVariantLabel($data['variants'][$i]['variant_definitions']);
                $variant->setFieldValue('vendProductVariantLabel', $variantLabel);

                $numberOfVariantDefinitions = count($data['variants'][$i]['variant_definitions']);
                if ($numberOfVariantDefinitions > 1) {
                    $variant->title = $this->getVariantTitle(json_decode($variantLabel));
                }

                $variants[] = $variant;
            }

        }

        $product->setVariants($variants);

        try {
            if (!Craft::$app->getElements()->saveElement($product)) {
                throw new \Exception();
            }
        } catch (Throwable $e) {
            Craft::error(
                "Can not save element 'Product'",
                __METHOD__
            );
        }
    }

    private function getProduct(string $id): ?array
    {
        try {
            $response = $this->api->getResponse("3.0/products/$id", [], true);
            return $response['data'];
        } catch (IdentityProviderException $e) {
            Craft::error(
                "Can not get product details from API for 'Product[$id]'",
                __METHOD__
            );
        }

        return null;
    }

    private function getInventory(string $id): ?array
    {
        try {
            $response = $this->api->getResponse("2.0/products/$id/inventory", [], true);
            return ArrayHelper::firstWhere($response['data'], 'outlet_id', Vend::$plugin->getSettings()->vend_outletId);
        } catch (IdentityProviderException $e) {
            Craft::error(
                "Can not get inventory details from API for 'Product[$id]'",
                __METHOD__
            );
        }

        return null;
    }

    private function getSupplierName(string $id): ?string
    {
        $name = null;

        try {
            $response = $this->api->getResponse("2.0/suppliers/$id", [], true);
            $name = $response['data']['name'];
        } catch (IdentityProviderException $e) {
            Craft::error(
                "Can not get supplier name from API for 'Supplier[$id]'",
                __METHOD__
            );
        }

        return $name;
    }

    private function getVariantLabel(array $definitions): ?string
    {
        if (count($definitions) === 1) {
            return $definitions[0]['name'];
        }

        if (count($definitions) > 1) {
            $names = array_map(function ($value) {
                return "\"$value\"";
            }, ArrayHelper::getColumn($definitions, 'name'));

            return '[' . implode(',', $names) . ']';
        }

        return null;
    }

    private function createProduct(string $id, array $data): Product
    {
        if (!isset(self::PRODUCT_TYPES[$data['product_type_id']])) {
            Craft::error(
                "Product type '{$data['type']['name']}' not recognized",
                __METHOD__
            );
        }

        $productTypeId = self::PRODUCT_TYPES[$data['product_type_id']];

        // Create product
        $product = new Product();
        $product->enabled = true;
        $product->title = $data['name'];
        $product->typeId = $productTypeId;
        $product->postDate = (new DateTime())->modify('+7 days');
        $product->postDate->setTimestamp($product->postDate->getTimestamp() - ($product->postDate->getTimestamp() % 60));
        $product->setFieldValue('vendProductId', $id);

        return $product;
    }

    private function getVariantTitle(array $variantOptions): string
    {
        return implode(', ', $variantOptions);
    }
}