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

use craft\web\Controller;
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
     * Import profiles index page.
     *
     * @return Response
     * @throws ForbiddenHttpException
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();
        // TODO
//        $parkedSales = Vend::$plugin->parkedSales->getAll();
        $parkedSales = [];

        return $this->renderTemplate('vend/parked-sales/_index', [
            'parkedSales' => $parkedSales
        ]);
    }
}