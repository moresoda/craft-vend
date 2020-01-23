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
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Throwable;
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
    // Public Methods
    // =========================================================================

    /**
     * Shows a list of the current webhooks created by this app.
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws IdentityProviderException
     */
    public function actionIndex(): Response
    {
        $this->requireAdmin();

        $variables = [];
        $vendApi = Vend::$plugin->api;

        // Current webhooks
        $webhooksResponse = $vendApi->getResponse('2.0/webhooks');

        $variables['webhooks'] = $webhooksResponse['data'];

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
        $this->requireAdmin();

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
        $entryQuery = Entry::find();
        $entryCriteria = [
            'limit' => 1,
            'vendProductId' => $vendProductId,
            'section' => 'vendProducts',
        ];
        Craft::configure($entryQuery, $entryCriteria);

        $entry = $entryQuery->one();
        if (!$entry) {
            // TODO logging
            return $this->asJson([
                'success' => false
            ]);
        }

        $elements = Craft::$app->getElements();

        $entry->vendInventoryCount = $inventoryAmount;
        if (!$elements->saveElement($entry)) {
            // TODO logging
            return $this->asJson([
                'success' => false
            ]);
        }

        // Get the Variant and update that
        $variantQuery = Variant::find();
        $variantCriteria = [
            'limit' => 1,
            'status' => null,
            'vendProductId' => $vendProductId
        ];
        Craft::configure($variantQuery, $variantCriteria);

        $variant = $variantQuery->one();
        if (!$variant) {
            // TODO logging
            return $this->asJson([
                'success' => false
            ]);
        }

        $variant->stock = $inventoryAmount;
        if (!$elements->saveElement($variant)) {
            // TODO logging
            return $this->asJson([
                'success' => false
            ]);
        }

        return $this->asJson([
            'success' => true
        ]);
    }
}