<?php

namespace angellco\vend\queue\jobs;

use angellco\vend\Vend;
use Craft;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\queue\BaseJob;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;

final class ProductUpdate extends BaseJob
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
     * @var int Vend Product ID
     */
    public $product_id;

    /**
     * @var int Vend Product ID
     */
    public $product_sku;

    /**
     * @var array Vend Product Data
     */
    protected $product;

    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $api = Vend::$plugin->api;

        try {
            $response = $api->getResponse("2.0/products/$this->product_id", [], true);
            $this->product = $response['data'];
        } catch (IdentityProviderException $e) {
            Craft::error(
                "Can not get details from API for 'Product[$this->product_id]'",
                __METHOD__
            );
        }

        if ($this->product['active'] === false) {
            $variantToDelete = Variant::find()->vendProductId($this->product_id)->one();

            if (Variant::find()->productId($variantToDelete->productId)->count() === 1) {
                if (!Craft::$app->getElements()->deleteElementById($variantToDelete->productId, true)) {
                    Craft::error(
                        "Can not delete element 'Product[$this->product_id]'",
                        __METHOD__
                    );
                }
            } else {
                // TODO: Handle changing Vend parent variant ID
                if (!Craft::$app->getElements()->deleteElement($variantToDelete, true)) {
                    Craft::error(
                        "Can not delete element 'Variant[$this->product_id]'",
                        __METHOD__
                    );
                }
            }

            return;
        }

        $isSimpleProduct = $this->product['variant_parent_id'] === null && count($this->product['variant_options']) === 0;
        $isVariantParentProduct = $this->product['variant_parent_id'] === null && count($this->product['variant_options']) > 0;
        $isVariantChildProduct = $this->product['variant_parent_id'] !== null && count($this->product['variant_options']) === 0;

        if ($isSimpleProduct) {
            $this->processSimpleProduct($this->product_id);
        }

        if ($isVariantParentProduct) {
            $this->processVariantParentProduct($this->product_id);
        }

        if ($isVariantChildProduct) {
            $this->processVariantChildProduct($this->product_id);
        }
    }

    /**
     * @param $id
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    private function processSimpleProduct($id): void
    {
        $product = Product::find()->vendProductId($id)->one();

        if (!$product) {
            $productTypeId = self::PRODUCT_TYPES[$this->product['type']['id']];

            if ($productTypeId === null) {
                Craft::error(
                    "Product type '{$this->product['type']['name']}' not recognized",
                    __METHOD__
                );
            }

            // Create product
            $product = new Product();
            $product->title = $this->product['name'];
            $product->typeId = $productTypeId;
            $product->enabled = false;
            $product->setFieldValue('vendProductId', $id);

            // Create variant
            $variant = new Variant();
            $variant->isDefault = true;
            $variant->hasUnlimitedStock = false;
            $variant->title = $this->product['name'];
            $variant->sku = $this->product['sku'];
            $variant->price = $this->product['price_including_tax'];
            $variant->setFieldValue('vendProductId', $id);
            $variant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name'] ?? '');

            $product->setVariants([$variant]);

            if (!Craft::$app->getElements()->saveElement($product)) {
                Craft::error(
                    "Can not save element 'Product'",
                    __METHOD__
                );
            }
        } else {
            $product->title = $this->product['name'];
            $product->defaultVariant->title = $this->product['name'];
            $product->defaultVariant->sku = $this->product['sku'];
            $product->defaultVariant->price = $this->product['price_including_tax'];
            $product->defaultVariant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name'] ?? '');

            if (!Craft::$app->getElements()->saveElement($product)) {
                Craft::error(
                    "Can not save element 'Product[$product->id]'",
                    __METHOD__
                );
            }
        }
    }

    /**
     * @param $id
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function processVariantParentProduct($id): void
    {
        $variant = Variant::find()->vendProductId($id)->one();

        if (!$variant) {
            $productTypeId = self::PRODUCT_TYPES[$this->product['type']['id']];

            if ($productTypeId === null) {
                Craft::error(
                    "Product type '{$this->product['type']['name']}' not recognized",
                    __METHOD__
                );
            }

            // Create product
            $product = new Product();
            $product->setFieldValue('vendProductId', $id);
            $product->title = $this->product['name'];
            $product->typeId = $productTypeId;
            $product->enabled = true;

            // Create variant
            $variant = new Variant();
            $variant->hasUnlimitedStock = false;
            $variant->title = $this->product['variant_options'][0]['value'];
            $variant->sku = $this->product['sku'];
            $variant->price = $this->product['price_including_tax'];
            $variant->setFieldValue('vendProductId', $id);
            $variant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name'] ?? '');
            $variant->setFieldValue('vendProductVariantLabel', $this->product['variant_options'][0]['name']);

            $product->setVariants([$variant]);

            if (!Craft::$app->getElements()->saveElement($product)) {
                Craft::error(
                    "Can not save element 'Product'",
                    __METHOD__
                );
            }
        } else {
            $variant->title = $this->product['variant_options'][0]['value'];
            $variant->sku = $this->product['sku'];
            $variant->price = $this->product['price_including_tax'];
            $variant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name']);
            $variant->setFieldValue('vendProductVariantLabel', $this->product['variant_options'][0]['name']);

            if (!Craft::$app->getElements()->saveElement($variant)) {
                Craft::error(
                    "Can not save element 'Variant[$variant->id]'",
                    __METHOD__
                );
            }
        }
    }

    /**
     * @param $id
     * @return void
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function processVariantChildProduct($id): void
    {
        $variant = Variant::find()->vendProductId($id)->one();

        if (!$variant) {
            $productTypeId = self::PRODUCT_TYPES[$this->product['type']['id']];

            if ($productTypeId === null) {
                Craft::error(
                    "Product type '{$this->product['type']['name']}' not recognized",
                    __METHOD__
                );
            }

            // Create variant
            $variant = new Variant();
            $variant->hasUnlimitedStock = false;
            $variant->title = $this->product['variant_options'][0]['value'];
            $variant->sku = $this->product['sku'];
            $variant->price = $this->product['price_including_tax'];
            $variant->setFieldValue('vendProductId', $id);
            $variant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name'] ?? '');
            $variant->setFieldValue('vendProductVariantLabel', $this->product['variant_options'][0]['name']);

            $product->setVariants([$variant]);

            if (!Craft::$app->getElements()->saveElement($product)) {
                Craft::error(
                    "Can not save element 'Product'",
                    __METHOD__
                );
            }
        } else {
            $variant->title = $this->product['variant_options'][0]['value'];
            $variant->sku = $this->product['sku'];
            $variant->price = $this->product['price_including_tax'];
            $variant->setFieldValue('vendProductSupplierName', $this->product['supplier']['name']);
            $variant->setFieldValue('vendProductVariantLabel', $this->product['variant_options'][0]['name']);

            if (!Craft::$app->getElements()->saveElement($variant)) {
                Craft::error(
                    "Can not save element 'Variant[$variant->id]'",
                    __METHOD__
                );
            }
        }
    }

    protected function defaultDescription(): string
    {
        return Craft::t('vend', 'Updating product');
    }
}