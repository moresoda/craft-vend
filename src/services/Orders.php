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
    public function registerSale(Order $order) {
        $vendApi = Vend::$plugin->api;
        $vendCustomerId = null;

        // Get the customer user from the order
        $customerUser = $order->getUser();

        // Store Vend customer ID if there is one
        if ($customerUser && $customerUser->vendCustomerId) {
            $vendCustomerId = $customerUser->vendCustomerId;
        }

        try {

            // If there isn’t one, make a fresh customer in Vend
            if (!$vendCustomerId) {

                // Get the billing address to make our customer object with
                $billingAddress = $order->getBillingAddress();

                if ($billingAddress) {
                    $vendCustomerObject = [
                        'first_name' => $billingAddress->firstName,
                        'last_name' => $billingAddress->lastName,
                        'email' => $order->getEmail()
                        // TODO customer group
                    ];

                    $customerResult = $vendApi->postRequest('2.0/customers', Json::encode($vendCustomerObject));

                    Craft::dd($customerResult);
                }
            }
            // Make the POST to register_sales: https://docs.vendhq.com/reference/0/spec/register-sales/createupdateregistersale
        } catch (\Exception $e) {
            throw $e;
        }

    }
}