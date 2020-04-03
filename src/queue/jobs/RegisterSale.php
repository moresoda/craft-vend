<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\queue\jobs;

use angellco\vend\Vend;
use Craft;
use craft\queue\BaseJob;
use craft\queue\QueueInterface;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\queue\Queue;

/**
 * RegisterSale job
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.3.0
 */
class RegisterSale extends BaseJob
{
    // Public Properties
    // =========================================================================

    /**
     * @var int|null The order ID of the order to send
     */
    public $orderId;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @param QueueInterface|Queue $queue
     *
     * @return mixed|void
     * @throws \Throwable
     * @throws Exception
     * @throws InvalidConfigException
     */
    public function execute($queue)
    {
        Vend::$plugin->orders->registerSale($this->orderId);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return Craft::t('vend', 'Registering sale with Vend for order ID: '.$this->orderId);
    }
}