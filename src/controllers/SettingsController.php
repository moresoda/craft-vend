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
     * Edit general plugin settings.
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

            }
        } catch (\Exception $e) {
            // Suppress the exception
            $variables['oauthAppMissing'] = true;
        }

        return $this->renderTemplate('vend/settings/general', $variables);
    }

    /**
     * Save the plugin settings.
     *
     * @return Response
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $settings = Vend::$plugin->getSettings();
        $settings->vend_registerSales = (bool) ($request->getBodyParam('vend_registerSales') ?? $settings->vend_registerSales);
        $settings->vend_userId = $request->getBodyParam('vend_userId') ?? $settings->vend_userId;
        $settings->vend_outletId = $request->getBodyParam('vend_outletId') ?? $settings->vend_outletId;
        $settings->vend_registerId = $request->getBodyParam('vend_registerId') ?? $settings->vend_registerId;
        $settings->vend_retailerPaymentTypeId = $request->getBodyParam('vend_retailerPaymentTypeId') ?? $settings->vend_retailerPaymentTypeId;

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

}