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
