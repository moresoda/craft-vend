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
use craft\web\Controller;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Parked Sales controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ParkedSalesController extends Controller
{
    // Public Methods
    // =========================================================================

    /**
     * Parked sales index page.
     *
     * @return Response
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();
        $parkedSales = Vend::$plugin->parkedSales->getAll();

        return $this->renderTemplate('vend/parked-sales/_index', [
            'parkedSales' => $parkedSales
        ]);
    }

    /**
     * Deletes a parked sale.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws Throwable
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $parkedSaleId = Craft::$app->request->getRequiredBodyParam('id');
        Vend::$plugin->parkedSales->deleteParkedSaleById($parkedSaleId);
        return $this->asJson([
            'success' => true
        ]);
    }
}