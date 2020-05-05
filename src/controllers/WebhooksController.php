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
use craft\commerce\elements\Variant;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\ServiceUnavailableHttpException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Throwable;
use yii\base\Action;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
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
    // Properties
    // =========================================================================
    protected $allowAnonymous = ['inventory'];

    // Public Methods
    // =========================================================================

    /**
     * @param Action $action
     *
     * @return bool
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws ServiceUnavailableHttpException
     */
    public function beforeAction($action)
    {
        if ($action->id === 'inventory') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    /**
     * Shows a list of the current webhooks created by this app.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws IdentityProviderException
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('vend:settings:webhooks');

        $variables = [];
        $vendApi = Vend::$plugin->api;

        // Current webhooks
        $webhooksResponse = $vendApi->getResponse('webhooks', [], true);

        $variables['webhooks'] = $webhooksResponse;

        return $this->renderTemplate('vend/settings/webhooks/index', $variables);
    }

    /**
     * Edit form for creating webhooks.
     *
     * @return Response
     * @throws ForbiddenHttpException
     */
    public function actionEdit(): Response
    {
        $this->requirePermission('vend:settings:webhooks');

        $variables = [];

        $variables[ 'title' ] = Craft::t('vend', 'Create a new webhook');

        // Breadcrumbs
        $variables['crumbs'] = [
            [
                'label' => Craft::t('vend', 'Vend Settings'),
                'url' => UrlHelper::url('vend/settings/general')
            ],
            [
                'label' => Craft::t('vend', 'Webhooks'),
                'url' => UrlHelper::url('vend/settings/webhooks')
            ]
        ];

        return $this->renderTemplate('vend/settings/webhooks/_edit', $variables);
    }

    /**
     * Saves a webhook with the Vend API.
     *
     * @return Response|null
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     * @throws MissingComponentException
     */
    public function actionSave()
    {
        $this->requirePermission('vend:settings:webhooks');
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $type = $request->getRequiredBodyParam('type');

        $url = false;
        if ($type === 'inventory.update') {
            $url = UrlHelper::actionUrl('vend/webhooks/inventory');
        }

        if (!$url) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Unsupported webhook type.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'type' => $type
            ]);

            return null;
        }

        // Prep the POST data - this is silly, but seems to be the only way it will go
        $data = urlencode('{"url":"'.$url.'","active":true,"type":"'.$type.'"}');

        // Make a POST request to the Vend API to create the endpoint
        try {
            Vend::$plugin->api->postRequest('webhooks', 'data='.$data,  [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
        } catch (\Exception $e) {

            Craft::$app->getSession()->setError(Craft::t('vend', 'Encountered an error with the Vend API.'));

            Craft::$app->getUrlManager()->setRouteParams([
                'type' => $type
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Webhook saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes a webhook from the API.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('vend:settings:webhooks');

        $webhookId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        // Make a DELETE request to the Vend API
        try {
            Vend::$plugin->api->deleteRequest("webhooks/{$webhookId}");
        } catch (\Exception $e) {
            return $this->asJson(['success' => false]);
        }

        return $this->asJson(['success' => true]);
    }

    // Webhook response methods
    // =========================================================================

    /**
     * Responds to the inventory.update webhook.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws Exception
     */
    public function actionInventory(): Response
    {
        $this->requirePostRequest();
        $settings = Vend::$plugin->getSettings();

        $request = Craft::$app->getRequest();

        $type = $request->getRequiredParam('type');
        $payload = $request->getRequiredParam('payload');
        $payload = Json::decode($payload);

        // Check it is the correct webhook and that we have the right data for the right outlet
        if ($type !== 'inventory.update' || !isset($payload['product_id'],$payload['count'],$payload['outlet_id']) || $payload['outlet_id'] !== $settings->vend_outletId)
        {
            return $this->asJson([
                'success' => false
            ]);
        }

        // Extract the relevant data
        $vendProductId = $payload['product_id'];
        $inventoryAmount = $payload['count'];

        // We need to update the product Entries first, in case for some reason
        // the actual Product feed runs before the Entries one updates
        $entry = Entry::findOne([
            'vendProductId' => $vendProductId,
            'section' => 'vendProducts',
        ]);

        if (!$entry) {
            Craft::error(
                'Error finding valid Entry for product ID: '.$vendProductId,
                __METHOD__
            );
            return $this->asJson([
                'success' => false
            ]);
        }

        $elements = Craft::$app->getElements();

        $entry->setFieldValue('vendInventoryCount', $inventoryAmount);
        if (!$elements->saveElement($entry)) {
            Craft::error(
                'Error updating Entry for product ID: '.$vendProductId,
                __METHOD__
            );
            Craft::info($entry->getErrors(), __METHOD__);
            return $this->asJson([
                'success' => false
            ]);
        }

        // Get the Variant and update that
        $variant = Variant::findOne([
            'limit' => 1,
            'status' => null,
            'vendProductId' => $vendProductId
        ]);

        if (!$variant) {
            Craft::error(
                'Error finding valid Variant for product ID: '.$vendProductId,
                __METHOD__
            );
            return $this->asJson([
                'success' => false
            ]);
        }

        $variant->stock = $inventoryAmount;
        if (!$elements->saveElement($variant)) {
            Craft::error(
                'Error updating Variant for product ID: '.$vendProductId,
                __METHOD__
            );
            Craft::info($variant->getErrors(), __METHOD__);
            return $this->asJson([
                'success' => false
            ]);
        }

        // Check the raw product json to see if its a composite
        $productJson = Json::decode($entry->vendProductJson);
        $compositeJson = Json::decode($entry->vendProductComposites);
        if ($productJson['is_composite'] === true && !empty($compositeJson)) {
            Vend::$plugin->products->updateInventoryForCompositeProductEntry($entry);
            // TODO: update the variant too in another service method
        }

        Craft::info(
            'Inventory webhook successfully executed.',
            __METHOD__
        );

        return $this->asJson([
            'success' => true
        ]);
    }
}