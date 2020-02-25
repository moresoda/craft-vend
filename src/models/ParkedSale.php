<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\models;

use craft\base\Model;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as CommercePlugin;
use craft\validators\DateTimeValidator;
use DateTime;

/**
 * ParkedSale model.
 *
 * @property Order|null $order
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ParkedSale extends Model
{
    // Public Properties
    // =========================================================================

    /**
     * @var int ID
     */
    public $id;

    /**
     * @var int Order ID
     */
    public $orderId;

    /**
     * @var DateTime|null
     */
    public $retryAfter;


    // Properties
    // =========================================================================

    private $_order;


    // Public Methods
    // =========================================================================

    /**
     * @return Order|null
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = CommercePlugin::getInstance()->getOrders()->getOrderById($this->orderId);
        }

        return $this->_order;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['id', 'orderId'], 'number', 'integerOnly' => true];
        return $rules;
    }
}