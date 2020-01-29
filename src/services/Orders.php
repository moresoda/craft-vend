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
use craft\commerce\Plugin as CommercePlugin;
use craft\helpers\Json;
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
     * @param Order $order
     *
     * @throws \Throwable
     */
    public function registerSale(Order $order) {
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

            // Make the POST to register_sales: https://docs.vendhq.com/reference/0/spec/register-sales/createupdateregistersale
            Craft::dd($vendCustomerId);

        } catch (\Exception $e) {
            throw $e;
        }

    }
}