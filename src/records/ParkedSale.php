<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\records;

use craft\commerce\elements\Order;
use craft\db\ActiveRecord;
use yii\db\ActiveQueryInterface;

/**
 * Parked Sale record.
 *
 * @property int                  $id         ID
 * @property int                  $orderId    Order ID
 * @property ActiveQueryInterface $order      The Order element
 * @property string               $retryAfter Retry after date time
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ParkedSale extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%vend_parkedsales}}';
    }

    /**
     * Returns the order.
     *
     * @return ActiveQueryInterface The relational query object.
     */
    public function getOrder(): ActiveQueryInterface
    {
        return $this->hasOne(Order::class, ['id' => 'orderId']);
    }
}