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

use Craft;
use craft\base\Component;
use craft\feedme\elements\CommerceOrder;

/**
 * Orders service.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Orders extends Component
{
    public function registerSale(CommerceOrder $order) {
        // TODO
        // Use API 2.0 to make customer if there isn’t one on the user already
        // If there is one, get its ID to use in the register sales call
        // Make the POST to register_sales: https://docs.vendhq.com/reference/0/spec/register-sales/createupdateregistersale
    }
}