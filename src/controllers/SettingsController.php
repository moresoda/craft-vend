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

use angellco\vend\models\Settings;
use angellco\vend\Vend;
use Craft;
use craft\commerce\Plugin as CommercePlugin;
use craft\errors\MissingComponentException;
use craft\web\Controller;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Settings controller.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class SettingsController extends Controller
{

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     *
     * @throws InvalidConfigException
     * @throws ForbiddenHttpException
     */
    public function init()
    {
        $this->requireAdmin();

        parent::init();
    }

    /**
     * Edit general settings.
     *
     * @return Response
     */
    public function actionEdit(): Response
    {
        $variables = [
            'oauthAppMissing' => false,
            'oauthToken' => null,
            'oauthProvider' => null,
            'settings' => Vend::$plugin->getSettings()
        ];

        // Get the OAuth token and provider so we know we are connected
        try {
            $vendApi = Vend::$plugin->api;

            if ($vendApi->oauthToken && $vendApi->oauthProvider) {

                // Store the basics
                $variables['oauthToken'] = $vendApi->oauthToken;
                $variables['oauthProvider'] = $vendApi->oauthProvider;

                // Customer Groups
                $vendCustomerGroups = $vendApi->getResponse('2.0/customer_groups');
                $variables['vendCustomerGroups'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendCustomerGroups['data']))
                {
                    foreach ($vendCustomerGroups['data'] as $vendCustomerGroup)
                    {
                        $variables['vendCustomerGroups'][] = [
                            'label' => $vendCustomerGroup['name'],
                            'value' => $vendCustomerGroup['id']
                        ];
                    }
                }

                // Users
                $vendUsers = $vendApi->getResponse('2.0/users');
                $variables['vendUsers'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendUsers['data']))
                {
                    foreach ($vendUsers['data'] as $vendUser)
                    {
                        $variables['vendUsers'][] = [
                            'label' => $vendUser['display_name'],
                            'value' => $vendUser['id']
                        ];
                    }
                }

                // Outlets
                $vendOutlets = $vendApi->getResponse('2.0/outlets');
                $variables['vendOutlets'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendOutlets['data']))
                {
                    foreach ($vendOutlets['data'] as $vendOutlet)
                    {
                        $variables['vendOutlets'][] = [
                            'label' => $vendOutlet['name'],
                            'value' => $vendOutlet['id']
                        ];
                    }
                }

                // Registers
                $vendRegisters = $vendApi->getResponse('2.0/registers');
                $variables['vendRegisters'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendRegisters['data']))
                {
                    foreach ($vendRegisters['data'] as $vendRegister)
                    {
                        $variables['vendRegisters'][] = [
                            'label' => $vendRegister['name'],
                            'value' => $vendRegister['id']
                        ];
                    }
                }

                // Payment types
                $vendPaymentTypes = $vendApi->getResponse('2.0/payment_types');
                $variables['vendPaymentTypes'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendPaymentTypes['data']))
                {
                    foreach ($vendPaymentTypes['data'] as $vendPaymentType)
                    {
                        $variables['vendPaymentTypes'][] = [
                            'label' => $vendPaymentType['name'],
                            'value' => $vendPaymentType['id']
                        ];
                    }
                }

                // Discount product
                $vendDiscountProducts = $vendApi->getResponse('products', [
                    'handle' => 'vend-discount',
                    'sku' => 'vend-discount'
                ]);
                $variables['vendDiscountProducts'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendDiscountProducts['products']))
                {
                    foreach ($vendDiscountProducts['products'] as $vendDiscountProduct)
                    {
                        $variables['vendDiscountProducts'][] = [
                            'label' => $vendDiscountProduct['name'],
                            'value' => $vendDiscountProduct['id']
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Suppress the exception
            $variables['oauthAppMissing'] = true;
        }

        return $this->renderTemplate('vend/settings/general', $variables);
    }

    /**
     * Save the general settings.
     *
     * @return Response
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();
        $settings->vend_registerSales = (bool) ($request->getBodyParam('vend_registerSales') ?? $settings->vend_registerSales);
        $settings->vend_customerGroupId = $request->getBodyParam('vend_customerGroupId') ?? $settings->vend_customerGroupId;
        $settings->vend_userId = $request->getBodyParam('vend_userId') ?? $settings->vend_userId;
        $settings->vend_outletId = $request->getBodyParam('vend_outletId') ?? $settings->vend_outletId;
        $settings->vend_registerId = $request->getBodyParam('vend_registerId') ?? $settings->vend_registerId;
        $settings->vend_retailerPaymentTypeId = $request->getBodyParam('vend_retailerPaymentTypeId') ?? $settings->vend_retailerPaymentTypeId;
        $settings->vend_discountProductId = $request->getBodyParam('vend_discountProductId') ?? $settings->vend_discountProductId;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/general', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Vend::$plugin, $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/general', compact('settings'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Edit tax settings.
     *
     * @return Response
     */
    public function actionEditTax(): Response
    {
        $variables = [
            'oauthAppMissing' => false,
            'oauthToken' => null,
            'oauthProvider' => null,
            'settings' => Vend::$plugin->getSettings()
        ];

        // Get the OAuth token and provider so we know we are connected
        try {
            $vendApi = Vend::$plugin->api;

            if ($vendApi->oauthToken && $vendApi->oauthProvider) {

                // Store the basics
                $variables['oauthToken'] = $vendApi->oauthToken;
                $variables['oauthProvider'] = $vendApi->oauthProvider;

                // Get taxes from the Vend API
                $vendTaxes = $vendApi->getResponse('2.0/taxes');
                $variables['vendTaxes'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendTaxes['data']))
                {
                    foreach ($vendTaxes['data'] as $vendTax)
                    {
                        $variables['vendTaxes'][] = [
                            'label' => $vendTax['name'],
                            'value' => $vendTax['id']
                        ];
                    }
                }

                // Get tax categories from Commerce
                $variables['taxCategories'] = CommercePlugin::getInstance()->getTaxCategories()->getAllTaxCategories();

            }
        } catch (\Exception $e) {
            // Suppress the exception
            $variables['oauthAppMissing'] = true;
        }

        return $this->renderTemplate('vend/settings/tax', $variables);
    }

    /**
     * Save the tax settings.
     *
     * @return Response
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveTax(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();
        $settings->taxMap = $request->getBodyParam('taxMap') ?? $settings->taxMap;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/tax', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Vend::$plugin, $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/tax', compact('settings'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Edit shipping settings.
     *
     * @return Response
     */
    public function actionEditShipping(): Response
    {
        $variables = [
            'oauthAppMissing' => false,
            'oauthToken' => null,
            'oauthProvider' => null,
            'settings' => Vend::$plugin->getSettings()
        ];

        // Get the OAuth token and provider so we know we are connected
        try {
            $vendApi = Vend::$plugin->api;

            if ($vendApi->oauthToken && $vendApi->oauthProvider) {

                // Store the basics
                $variables['oauthToken'] = $vendApi->oauthToken;
                $variables['oauthProvider'] = $vendApi->oauthProvider;

                // Get product types from the Vend API
                $vendProductTypes = $vendApi->getResponse('2.0/product_types');
                $variables['vendProductTypes'] = [
                    [
                        'label' => '',
                        'value' => ''
                    ]
                ];
                if (isset($vendProductTypes['data']))
                {
                    foreach ($vendProductTypes['data'] as $vendProductType)
                    {
                        $variables['vendProductTypes'][] = [
                            'label' => $vendProductType['name'],
                            'value' => $vendProductType['id']
                        ];
                    }
                }

                // Get the shipping rules
                $variables['shippingRules'] = CommercePlugin::getInstance()->getShippingRules()->getAllShippingRules();

            }
        } catch (\Exception $e) {
            // Suppress the exception
            $variables['oauthAppMissing'] = true;
        }

        return $this->renderTemplate('vend/settings/shipping', $variables);
    }

    /**
     * Returns a list of products for a given product type.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws IdentityProviderException
     */
    public function actionGetShippingProducts(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $vendApi = Vend::$plugin->api;

        // Get the product type ID
        $typeId = $request->getBodyParam('typeId');

        // Get products in that product type
        $vendProducts = $vendApi->getResponse('2.0/search', [
            'type' => 'products',
            'product_type_id' => $typeId
        ]);

        $productOptions = [];
        if (isset($vendProducts['data'])) {
            foreach ($vendProducts['data'] as $vendProduct)
            {
                $productOptions[] = [
                    'label' => $vendProduct['name'],
                    'value' => $vendProduct['id']
                ];
            }
        } else {
            return $this->asJson([
                'success' => false
            ]);
        }

        return $this->asJson([
            'success' => true,
            'products' => $productOptions
        ]);
    }

    /**
     * Save the shipping settings.
     *
     * @return Response
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSaveShipping(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        /** @var Settings $settings */
        $settings = Vend::$plugin->getSettings();
        $settings->shippingMap = $request->getBodyParam('shippingMap') ?? $settings->shippingMap;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/tax', compact('settings'));
        }

        $pluginSettingsSaved = Craft::$app->getPlugins()->savePluginSettings(Vend::$plugin, $settings->toArray());

        if (!$pluginSettingsSaved) {
            Craft::$app->getSession()->setError(Craft::t('vend', 'Couldn’t save settings.'));
            return $this->renderTemplate('vend/settings/tax', compact('settings'));
        }

        Craft::$app->getSession()->setNotice(Craft::t('vend', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

}