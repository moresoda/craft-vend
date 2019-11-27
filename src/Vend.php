<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\web\Controller;
use venveo\oauthclient\events\TokenEvent;
use venveo\oauthclient\services\Providers;
use venveo\oauthclient\services\Tokens;
use angellco\vend\models\Settings;
use angellco\vend\oauth\providers\VendVenveo as VendProvider;
use angellco\vend\services\Api as VendApi;
use yii\base\Event;

/**
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 *
 * @property VendApi $api
 * @property \yii\web\Response|mixed $settingsResponse
 */
class Vend extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * Vend::$plugin
     *
     * @var Vend
     */
    public static $plugin;

    // Public Properties
    // =========================================================================

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '2.0.0';

    // Public Methods
    // =========================================================================

    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * Vend::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Install our event listeners
        $this->installEventListeners();

//        $plugin = \venveo\oauthclient\Plugin::$plugin;
//// Let's grab a valid token - we could pass the current user ID in here to limit it
//        $tokens = $plugin->credentials->getValidTokensForAppAndUser('vend');
//// Get the app from the apps service
//        $app = $plugin->apps->getAppByHandle('vend');
//
//
//
//        /** @var \angellco\vend\oauth\providers\Vend $provider */
//        $provider = $app->getProviderInstance()->getConfiguredProvider();
//        $url = $provider->getApiUrl('users');
//        $request = $provider->getAuthenticatedRequest('GET', $url, $tokens[0]);
//
//        $app->getProviderInstance()->getConfiguredProvider()->getParsedResponse($request);




//        Craft::$app->plugins->isPluginInstalled('oauthclient');


//        // Add our key resources
//        if ( craft()->request->isCpRequest() && craft()->userSession->isLoggedIn() )
//        {
//
//            craft()->templates->includeCssResource('vend/css/vend.css');
//
//            if ((craft()->request->getSegment(1) == 'commerce' && craft()->request->getSegment(2) == 'products') || craft()->request->getSegment(1) == 'vend')
//            {
//                craft()->templates->includeJsResource('vend/js/vend.js');
//                craft()->templates->includeJs("new Vend.Sync()");
//            }
//
//        }
//
//        // Bind to the order complete event so we can register the sale with Vend
//        // but only if the settings allow us to ;)
//        if ($this->getSettings()->commerce_registerSales) {
//            craft()->on('commerce_orders.onOrderComplete', function(Event $event)
//            {
//                craft()->vend->registerSale($event->params['order']);
//            });
//        }

        // Log on load for debugging
        Craft::info(
            Craft::t(
                'vend',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Returns the settings page response.
     *
     * @return mixed|\yii\web\Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function getSettingsResponse()
    {

        $variables = [
            'oauthAppMissing' => false,
            'oauthToken' => null,
            'oauthProvider' => null,
        ];

        // Get the OAuth token and provider so we know we are connected
        try {
            $vendApi = $this->api;

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


        $variables['settings'] = $this->getSettings();

        // Load up our settings template
        $view = Craft::$app->getView();
        $namespace = $view->getNamespace();
        $view->setNamespace('settings');
        $settingsHtml = $view->renderTemplate('vend/_settings.html', $variables);
        $view->setNamespace($namespace);

        /** @var Controller $controller */
        $controller = Craft::$app->controller;

        return $controller->renderTemplate('settings/plugins/_settings', [
            'plugin' => $this,
            'settingsHtml' => $settingsHtml
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Install our event listeners.
     */
    protected function installEventListeners(): void
    {
        // Registers our provider with the Venveo OAuth plugin
        Event::on(
            Providers::class,
            Providers::EVENT_REGISTER_PROVIDER_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = VendProvider::class;
            }
        );

        // Save the domain prefix into the plugin settings
        Event::on(
            Tokens::class,
            Tokens::EVENT_BEFORE_TOKEN_SAVED,
            static function (TokenEvent $e) {
                $domainPrefix = Craft::$app->getRequest()->getRequiredQueryParam('domain_prefix');
                Craft::$app->getPlugins()->savePluginSettings(self::$plugin, [
                    'domainPrefix' => $domainPrefix
                ]);
            }
        );
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings|null
     */
    protected function createSettingsModel(): ?Settings
    {
        return new Settings();
    }




//{% set oauthTokens = craft.oauth.credentials.getValidTokensForAppAndUser('vend') %}

}
