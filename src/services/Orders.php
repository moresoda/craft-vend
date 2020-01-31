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
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\Json;
use Throwable;
use yii\base\InvalidConfigException;
use yii\web\NotFoundHttpException;

/**
 * Orders service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Orders extends Component
{

    /**
     * Sends the order to Vend.
     *
     * @param int $orderId
     *
     * @return mixed
     * @throws InvalidConfigException
     * @throws Throwable
     */
    public function registerSale(int $orderId) {
        // Get the order
        $order = CommercePlugin::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) {
            return false;
        }

        $vendApi = Vend::$plugin->api;
        $vendCustomerId = null;
        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();

        // Get the bits we need from the order
        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();
        $customerUser = $order->getUser();
        $email = $order->getEmail();

        // Store Vend customer ID if there is one
        if ($customerUser && $customerUser->vendCustomerId) {
            $vendCustomerId = $customerUser->vendCustomerId;
        }

        /**
         * First, sort out the customer
         */
        try {

            // Make the Vend customer object
            $vendCustomerObject = null;
            if ($email && $billingAddress && $shippingAddress) {

                // Make the customer
                $vendCustomerObject = [
                    'customer_group_id' => $settings->vend_customerGroupId,
                    'email' => $email,
                    'first_name' => $billingAddress->firstName,
                    'last_name' => $billingAddress->lastName,
                    'phone' => $billingAddress->phone,
                    'company_name' => $billingAddress->businessName,

                    'physical_address_1' => $billingAddress->address1,
                    'physical_address_2' => $billingAddress->address2,
                    'physical_suburb' => $billingAddress->address3,
                    'physical_city' => $billingAddress->city,
                    'physical_postcode' => $billingAddress->zipCode,
                    'physical_state' => $billingAddress->getStateText(),
                    'physical_country_id' => $billingAddress->getCountry()->iso,

                    'postal_address_1' => $shippingAddress->address1,
                    'postal_address_2' => $shippingAddress->address2,
                    'postal_suburb' => $shippingAddress->address3,
                    'postal_city' => $shippingAddress->city,
                    'postal_postcode' => $shippingAddress->zipCode,
                    'postal_state' => $shippingAddress->getStateText(),
                    'postal_country_id' => $shippingAddress->getCountry()->iso
                ];

                // If there is currently no customer in Vend, then make a new one
                if (!$vendCustomerId) {
                    $customerResult = $vendApi->postRequest('2.0/customers', Json::encode($vendCustomerObject), [
                        'Content-Type' => 'application/json',
                    ]);

                    // Save the ID back onto our Craft User
                    $vendCustomerId = $customerResult['data']['id'];
                    $customerUser->setFieldValue('vendCustomerId', $vendCustomerId);
                    Craft::$app->getElements()->saveElement($customerUser);
                } else {
                    // There is a customer, but we could update it couldn’t we now
                    $vendApi->putRequest("2.0/customers/{$vendCustomerId}", Json::encode($vendCustomerObject), [
                        'Content-Type' => 'application/json',
                    ]);
                }
            }

        } catch (\Exception $e) {
            // TODO: logging
            throw $e;
        }


        /**
         * Second, make the data we need to register a sale
         */

        // Prep the basic minimum we need to register a sale
        $data = [
            'source_id' => $order->number,
            'register_id' => $settings->vend_registerId,
            'customer_id' => $vendCustomerId,
            'user_id' => $settings->vend_userId,
            'status' => 'CLOSED',
            'sale_date' => $order->dateOrdered->format('Y-m-d H:i:s'),
            'register_sale_products' => [],
            'register_sale_payments' => [
                [
                    'retailer_payment_type_id' => $settings->vend_retailerPaymentTypeId,
                    'payment_date' => $order->datePaid->format('Y-m-d H:i:s'),
                    'amount' => $order->getTotalPaid()
                ]
            ]
        ];

        // Process the line items
        /** @var LineItem $lineItem */
        foreach ($order->getLineItems() as $lineItem) {

            /** @var Variant $variant */
            $variant = $lineItem->getPurchasable();

            // Check we actually got a Commerce Variant - we don’t want to
            // accidentally support other purchasables for now
            if (is_a($variant, Variant::class)) {

                // Work out the sales tax ID
                $taxCategory = $lineItem->getTaxCategory();
                if (!$taxCategory || !isset($settings->taxMap[$taxCategory->id])) {
                    continue;
                }
                $salesTaxId = $settings->taxMap[$taxCategory->id];

                // Find the amount of tax for one item
                $taxAmount = bcdiv($lineItem->getTaxIncluded(), $lineItem->qty, 5);

                // Prep the main product data array
                $productData = [
                    'product_id' => $variant->vendProductId,
                    'quantity' => $lineItem->qty,
                    // Unit price, tax exclusive
                    'price' => bcsub($lineItem->salePrice, $taxAmount, 5),
                    // The amount of tax in the unit price
                    'tax' => $taxAmount, // TODO: check this includes the discount, if not we need to factor it in
                    // The applicable Sales Tax ID
                    'tax_id' => $salesTaxId
                ];

                // Add the discount for this line item if there is one
                if ($lineItem->getDiscount()) {
                    $productData['discount'] = $lineItem->getDiscount();
                    $productData['price_set'] = 1;
                }

                // Add the no†e if there is one
                if (!empty($lineItem->note)) {
                    $productData['attributes'][] = [
                        'name' => 'line_note',
                        'value' => $lineItem->note
                    ];
                }

                // Finally tack the product onto our main data stack
                $data['register_sale_products'][] = $productData;
            }

        }

        // Process the active shipping rule
        $shippingMethod = $order->getShippingMethod();
        if ($shippingMethod) {
            $shippingRule = $shippingMethod->getMatchingShippingRule($order);
            if ($shippingRule && isset($settings->shippingMap['rules'][$shippingRule->id])) {

                $ruleSettings = $settings->shippingMap['rules'][$shippingRule->id];

                $data['register_sale_products'][] = [
                    'product_id' => $ruleSettings['productId'],
                    'quantity' => 1,
                    'price' => $ruleSettings['productPrice']['excludingTax'],
                    'tax' => bcsub($ruleSettings['productPrice']['includingTax'], $ruleSettings['productPrice']['excludingTax'], 5),
                    'tax_id' => $ruleSettings['taxId']
                ];
            }
        }

        // Process order level discount adjustments
        $totalDiscount = abs($order->getTotalDiscount());
        if ($totalDiscount > 0) {
            $data['register_sale_products'][] = [
                'product_id' => $settings->vend_discountProductId,
                'quantity' => -1,
                'price' => $totalDiscount,
                'price_set' => 1,
                'tax' => 0,
                'tax_id' => $settings->vend_noTaxId
            ];
        }

//        Craft::dd($data);

        /**
         * Finally, send the sale to Vend
         */
        try {
            return $vendApi->postRequest('register_sales', Json::encode($data));
        } catch (\Exception $e) {
            // TODO: logging
            throw $e;
        }
    }
}