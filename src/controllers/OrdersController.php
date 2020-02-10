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

use angellco\vend\Vend;
use Craft;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use Throwable;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Orders controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class OrdersController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * @return Response|null
     * @throws Throwable
     * @throws MissingComponentException
     * @throws InvalidConfigException
     * @throws BadRequestHttpException
     */
    public function actionSend()
    {
        $orderId = Craft::$app->getRequest()->getRequiredParam('id');

        // Remove all parked sales for this order in advance - if the current
        // operation fails it will just create another one anyway
        Vend::$plugin->parkedSales->deleteParkedSalesByOrderId($orderId);

        if (!Vend::$plugin->orders->registerSale($orderId)) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t register sale with Vend.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Sale registered with Vend.'));

        return $this->redirect('vend/parked-sales');
    }
}