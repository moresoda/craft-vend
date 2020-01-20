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
use craft\helpers\Json;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Webhooks controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class WebhooksController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     * Responds to the inventory.update webhook.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionInventory(): Response
    {
//        $this->requirePostRequest();// DEBUG
        $settings = Vend::$plugin->getSettings();

        $request = Craft::$app->getRequest();

        $type = $request->getRequiredParam('type');
//        $payload = $request->getRequiredParam('payload');// DEBUG
        $payload = Json::decode('{"outlet_id":"b8ca3a65-011c-11e4-f728-e521433cf52f","count":"100","product_id":"02e60bb7-8d7a-11e9-f4c2-da07f6f36cf2"}');

        // Check it is the correct webhook and that we have the right data for the right outlet
        if ($type !== 'inventory.update' || !isset($payload['product_id'],$payload['count'],$payload['outlet_id']) || $payload['outlet_id'] !== $settings->vend_outletId)
        {
            return $this->asJson([
                'success' => false
            ]);
        }

        $vendProductId = $payload['product_id'];
        $inventoryAmount = $payload['count'];

        Craft::dd($vendProductId);

//        // Get the Variant
//        $criteria = craft()->elements->getCriteria('Commerce_Variant');
//        $criteria->limit = null;
//        $criteria->status = null;
//        $criteria->enabled = null;
//        $criteria[$settings->variantVend_id] = $vendId;
//        $variant = $criteria->first();
//
//            // Check we have a Variant, bail if not
//            if (!$variant) {
//                $this->returnJson(array(
//                    'success' => true
//                ));
//            }
//
//            // Set the stock level
//            $variant->setAttributes(array(
//                'stock' => $newStock
//            ));
//
//            // Try and commit the transaction
//            CommerceDbHelper::beginStackedTransaction();
//            try
//            {
//                craft()->commerce_variants->saveVariant($variant);
//            }
//            catch (Exception $e)
//            {
//                CommerceDbHelper::rollbackStackedTransaction();
//                VendPlugin::log(Craft::t('Couldn’t save a stock of {stock} on the Variant with a Vend ID of {id}.', array('stock' => $stock, 'id' => $payload['product_id'])), LogLevel::Error);
//                return false;
//            }
//            CommerceDbHelper::commitStackedTransaction();

        return $this->asJson([
            'success' => true
        ]);

    }
}