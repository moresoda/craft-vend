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
use venveo\oauthclient\events\TokenEvent;
use venveo\oauthclient\services\Providers;
use angellco\vend\oauth\providers\VendVenveo as VendProvider;
use venveo\oauthclient\services\Tokens;
use yii\base\Event;

/**
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
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
    public function init()
    {
        parent::init();
        self::$plugin = $this;


        Event::on(
            Providers::class,
            Providers::EVENT_REGISTER_PROVIDER_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $event->types[] = VendProvider::class;
            }
        );


        // TODO: Save the domain prefix onto the User so we always have access to it
        Event::on(
            Tokens::class,
            Tokens::EVENT_BEFORE_TOKEN_SAVED,
            static function (TokenEvent $e) {
                $domainPrefix = Craft::$app->getRequest()->getRequiredQueryParam('domain_prefix');

                // Save onto user

                // Then, in provider send it to constructor somehow
                Craft::dd($domainPrefix);
            }
        );

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
//        Craft::dd($app->getProviderInstance()->getConfiguredProvider()->getParsedResponse($request));




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

}
