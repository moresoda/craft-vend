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
use angellco\vend\services\Orders;
use angellco\vend\services\ParkedSales;
use angellco\vend\web\assets\orders\EditOrderAsset;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Field;
use craft\base\Plugin;
use craft\base\PreviewableFieldInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\log\FileTarget;
use craft\web\UrlManager;
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
 * @property VendApi        $api
 * @property ImportProfiles $importProfiles
 * @property Orders         $orders
 * @property ParkedSales    $parkedSales
 * @property array          $cpNavItem
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
    public $schemaVersion = '2.2.0';

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

        // Custom logger
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/vend.log'),
            'categories' => ['angellco\vend\*'],
        ]);

        // Load up our order edit stuff
        $view = Craft::$app->getView();
        $view->hook('cp.commerce.order.edit', static function(array &$context) use($view) {

            /** @var Order $order */
            $order = $context['order'];
            if ($order->isCompleted) {
                // TODO: check its a Vend order
                $view->registerAssetBundle(EditOrderAsset::class);
                $view->registerJs('new Craft.Vend.OrderEdit({commerceOrderId:"'.$order->id.'",vendOrderId:"'.$order->vendOrderId.'"});');
            }

        });

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
     * @return \craft\web\Response|mixed|\yii\console\Response|Response
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

                $event->rules['vend/settings/import-profiles'] = 'vend/import-profiles/index';
                $event->rules['vend/settings/import-profiles/new'] = 'vend/import-profiles/edit';
                $event->rules['vend/settings/import-profiles/<profileId:\d+>'] = 'vend/import-profiles/edit';

                $event->rules['vend/settings/webhooks'] = 'vend/webhooks/index';
                $event->rules['vend/settings/webhooks/new'] = 'vend/webhooks/edit';

                $event->rules['vend/settings/feed-me'] = 'vend/settings/feed-me';
                $event->rules['vend/settings/shipping'] = 'vend/settings/edit-shipping';
                $event->rules['vend/settings/tax'] = 'vend/settings/edit-tax';
                $event->rules['vend/settings/general'] = 'vend/settings/edit';
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

        // Bind to the order complete event so we can register the sale with Vend
        if ($this->getSettings()->vend_registerSales) {
            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                function(Event $e) {
                    /** @var Order $order */
                    $order = $e->sender;
                    $this->orders->registerSale($order->id);
                }
            );
        }

        // Customise the Vend Order ID field on order the index
        if ($this->getSettings()->domainPrefix) {
            /** @var Field $vendOrderIdField */
            $vendOrderIdField = Craft::$app->getFields()->getFieldByHandle('vendOrderId');
            if ($vendOrderIdField) {
                Event::on(
                    Order::class,
                    Order::EVENT_SET_TABLE_ATTRIBUTE_HTML,
                    function(Event $e) use ($vendOrderIdField) {
                        if ($e->attribute === "field:{$vendOrderIdField->id}") {

                            /** @var Order $order */
                            $order = $e->sender;

                            // Check its completed
                            if ($order->isCompleted) {

                                // Check its a Vend Order as well
                                $firstLineItem = $order->getLineItems()[0];
                                if (is_a($firstLineItem->getPurchasable(), Variant::class)) {
                                    if ($vendOrderIdField instanceof PreviewableFieldInterface) {
                                        // Was this field value eager-loaded?
                                        if ($vendOrderIdField instanceof EagerLoadingFieldInterface && $order->hasEagerLoadedElements($vendOrderIdField->handle)) {
                                            $value = $order->getEagerLoadedElements($vendOrderIdField->handle);
                                        } else {
                                            // The field might not actually belong to this element
                                            try {
                                                $value = $order->getFieldValue($vendOrderIdField->handle);
                                            } catch (\Throwable $e) {
                                                $value = $vendOrderIdField->normalizeValue(null);
                                            }
                                        }

                                        // If we have a value, show the link
                                        if ($value) {
                                            $e->html = "<a href='https://{$this->getSettings()->domainPrefix}.vendhq.com/redirect/1.0/sales/{$value}?action=view' class='go' target='_blank'>View on Vend</a>";
                                        } else {
                                            // If we don’t, show an error
                                            $e->html = "<span class='error'>Not yet on Vend</span>";
                                        }
                                    }
                                } else {
                                    $e->html = 'Not a Vend Order.';
                                }
                            }
                        }
                    }
                );
            }
        }

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
