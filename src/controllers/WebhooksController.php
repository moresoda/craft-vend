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

use angellco\vend\queue\jobs\UpdateProduct;
use angellco\vend\Vend;
use Craft;
use craft\errors\MissingComponentException;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\helpers\Queue;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\ServiceUnavailableHttpException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use yii\base\Action;
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
    protected $allowAnonymous = ['inventory', 'product'];

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
        if (ArrayHelper::isIn($action->id, ['inventory', 'product'])) {
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
        switch ($type) {
            case 'inventory.update':
                $url = UrlHelper::actionUrl('vend/webhooks/inventory');
                break;
            case 'product.update':
                $url = UrlHelper::actionUrl('vend/webhooks/product');
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
     */
    public function actionInventory(): Response
    {
        $this->requirePostRequest();
        $settings = Vend::$plugin->getSettings();

        $request = Craft::$app->getRequest();
        $vendProducts = Vend::$plugin->products;

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

        // Update the Entry record
        if (!$vendProducts->updateInventoryForEntry($vendProductId, $inventoryAmount)) {
            return $this->asJson([
                'success' => false
            ]);
        }

        // Update the Variant
        if (!$vendProducts->updateInventoryForVariant($vendProductId, $inventoryAmount)) {
            return $this->asJson([
                'success' => false
            ]);
        }

        return $this->asJson([
            'success' => true
        ]);
    }

    /**
     * Responds to the inventory.update webhook.
     *
     * @return Response
     * @throws BadRequestHttpException
     */
    public function actionProduct(): Response
    {
        $this->requirePostRequest();

        $webhookId = $this->request->getHeaders()->get('x-vend-webhook-id');

        $type = $this->request->getParam('type');
        $payload = Json::decode($this->request->getRequiredParam('payload'));

        $isWrongWebhookType = $type !== 'product.update';
        $hasNoProductId = !isset($payload['id']);
        $isProductVariant = isset($payload['variant_parent_id']);

        // Check it is the correct webhook and that we have the right data for the right outlet
        if ($isWrongWebhookType || $hasNoProductId || $isProductVariant) {
            // Send success message to Vend and silently drop webhook
            return $this->asJson([
                'success' => false
            ]);
        }

        $productId = $payload['id'];

        // Acquire mutex lock to ensure we only queue single job
        if (Craft::$app->getMutex()->acquire(md5("product_id:$productId;webhook_id:$webhookId;"))) {
            // Log Product ID and Webhook ID
            Craft::info("product_id:$productId;webhook_id:$webhookId;", 'vend\webhooks\product');
            Craft::debug($payload, 'vend\webhooks\product');

            // Dispatch job to process product update
            Queue::push(new UpdateProduct(['product_id' => $productId]));
        }

        return $this->asJson([
            'success' => true
        ]);
    }
}