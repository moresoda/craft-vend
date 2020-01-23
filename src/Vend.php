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

use angellco\vend\models\Settings;
use angellco\vend\oauth\providers\VendVenveo as VendProvider;
use angellco\vend\services\Api as VendApi;
use angellco\vend\services\ImportProfiles;
use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\UrlManager;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use venveo\oauthclient\events\TokenEvent;
use venveo\oauthclient\services\Providers;
use venveo\oauthclient\services\Tokens;
use yii\base\Event;
use yii\web\Response;

/**
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 *
 * @property VendApi $api
 * @property ImportProfiles $importProfiles
 * @property Response|mixed $settingsResponse
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

        // Install our event listeners
        $this->installEventListeners();

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
     * @return mixed|Response
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getSettingsResponse()
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('vend/settings'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): array
    {
        $ret = parent::getCpNavItem();

        $ret['label'] = Craft::t('vend', 'Vend');

        $ret['subnav']['parked-sales'] = [
            'label' => Craft::t('vend', 'Parked Sales'),
            'url' => 'vend/parked-sales'
        ];

        if (Craft::$app->getUser()->getIsAdmin() && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $ret['subnav']['import-profiles'] = [
                'label' => Craft::t('vend', 'Import Profiles'),
                'url' => 'vend/import-profiles'
            ];

            $ret['subnav']['settings'] = [
                'label' => Craft::t('app', 'Settings'),
                'url' => 'vend/settings'
            ];
        }

        return $ret;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Install our event listeners.
     */
    protected function installEventListeners()
    {
        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function(RegisterUrlRulesEvent $event) {
                $event->rules['vend/parked-sales'] = 'vend/parked-sales/index';

                $event->rules['vend/import-profiles'] = 'vend/import-profiles/index';
                $event->rules['vend/import-profiles/new'] = 'vend/import-profiles/edit';
                $event->rules['vend/import-profiles/<profileId:\d+>'] = 'vend/import-profiles/edit';

                $event->rules['vend/settings'] = 'vend/settings/edit';
            }
        );

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
                $domainPrefix = Craft::$app->getRequest()->getQueryParam('domain_prefix');
                if ($domainPrefix) {
                    Craft::$app->getPlugins()->savePluginSettings(self::$plugin, [
                        'domainPrefix' => $domainPrefix
                    ]);
                }
            }
        );

        // Project config listeners
        Craft::$app->projectConfig
            ->onAdd($this->importProfiles::CONFIG_PROFILES_KEY.'.{uid}', [$this->importProfiles, 'handleChangedProfile'])
            ->onUpdate($this->importProfiles::CONFIG_PROFILES_KEY.'.{uid}', [$this->importProfiles, 'handleChangedProfile'])
            ->onRemove($this->importProfiles::CONFIG_PROFILES_KEY.'.{uid}', [$this->importProfiles, 'handleDeletedProfile']);

        // Project config rebuild listener
//        Event::on(ProjectConfig::class, ProjectConfig::EVENT_REBUILD, function(RebuildConfigEvent $e) {
//            $e->config[BlockTypes::CONFIG_BLOCKTYPE_KEY] = ProjectConfigHelper::rebuildProjectConfig();
//        });
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

}
