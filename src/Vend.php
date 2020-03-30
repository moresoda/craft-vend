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
use angellco\vend\widgets\FastFeed;
use angellco\vend\widgets\FullFeed;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Field;
use craft\base\Plugin;
use craft\base\PreviewableFieldInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Variant;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\models\FeedModel;
use craft\feedme\Plugin as FeedMe;
use craft\feedme\queue\jobs\FeedImport;
use craft\feedme\services\Process;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\log\FileTarget;
use craft\services\Dashboard;
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
    public $schemaVersion = '2.2.4';

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

        $this->setupLogging();
        $this->installEventListeners();
        $this->attachToHooks();
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

        $ret['subnav']['sync'] = [
            'label' => Craft::t('vend', 'Sync'),
            'url' => 'vend/sync'
        ];

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

        // Widgets
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = FullFeed::class;
                $event->types[] = FastFeed::class;
            }
        );

        // Feed Me listeners
        Event::on(
            Process::class,
            Process::EVENT_AFTER_PROCESS_FEED,
            static function(FeedProcessEvent $event) {
                /** @var FeedModel $feed */
                $currentFeed = $event->feed;
                $feeds = FeedMe::$plugin->getFeeds();
                $queue = Craft::$app->getQueue();

                // Fast sync - main product db import
                if (StringHelper::containsAll($currentFeed->feedUrl, ['vend/products/list', 'fastSyncLimit', 'fastSyncOrder'])) {

                    // Get the fastSyncLimit and fastSyncOrder params out of the feed URL
                    $parts = parse_url($currentFeed->feedUrl);
                    parse_str($parts['query'], $query);
                    $fastSyncLimit = $query['fastSyncLimit'];
                    $fastSyncOrder = $query['fastSyncOrder'];

                    // Trigger all the product import feeds but modify their URLs to be fast versions
                    $runQueue = false;
                    foreach ($feeds->getFeeds() as $feed) {

                        if (StringHelper::contains($feed->feedUrl, 'vend/products/import')) {

                            // Modify the feed URL to include the limit, order and inline inventory trigger
                            $feed->feedUrl = UrlHelper::urlWithParams($feed->feedUrl, [
                                'limit' => $fastSyncLimit,
                                'inventory' => 1,
                                'order' => $fastSyncOrder
                            ]);

                            $processedElementIds = [];

                            $queue->delay(0)->push(new FeedImport([
                                'feed' => $feed,
                                'limit' => null,
                                'offset' => null,
                                'processedElementIds' => $processedElementIds,
                            ]));

                            $runQueue = true;
                        }

                    }

                    if ($runQueue) {
                        $queue->run();
                    }

                // Full sync - main product db import
                } elseif (StringHelper::contains($currentFeed->feedUrl, 'vend/products/list')) {

                    // Trigger inventory
                    foreach ($feeds->getFeeds() as $feed) {

                        if (StringHelper::contains($feed->feedUrl, 'vend/products/inventory')) {

                            $processedElementIds = [];

                            $queue->delay(0)->push(new FeedImport([
                                'feed' => $feed,
                                'limit' => null,
                                'offset' => null,
                                'processedElementIds' => $processedElementIds,
                            ]));

                            $queue->run();

                            break;
                        }
                    }

                // Main inventory feed
                } elseif (StringHelper::contains($currentFeed->feedUrl, 'vend/products/inventory')) {

                    // Trigger all of the full product import feeds
                    $runQueue = false;
                    foreach ($feeds->getFeeds() as $feed) {
                        if (StringHelper::contains($feed->feedUrl, 'vend/products/import')) {
                            $processedElementIds = [];

                            $queue->delay(0)->push(new FeedImport([
                                'feed' => $feed,
                                'limit' => null,
                                'offset' => null,
                                'processedElementIds' => $processedElementIds,
                            ]));

                            $runQueue = true;
                        }
                    }

                    if ($runQueue) {
                        $queue->run();
                    }
                }

            }
        );
    }

    /**
     * Attach to any hooks.
     */
    protected function attachToHooks()
    {
        $view = Craft::$app->getView();

        // Load up our order edit stuff
        $view->hook('cp.commerce.order.edit', static function(array &$context) use($view) {

            /** @var Order $order */
            $order = $context['order'];
            if ($order->isCompleted) {
                // TODO: check its a Vend order
                $view->registerAssetBundle(EditOrderAsset::class);
                $view->registerJs('new Craft.Vend.OrderEdit({commerceOrderId:"'.$order->id.'",vendOrderId:"'.$order->vendOrderId.'"});');
            }

        });
    }

    /**
     * Setup the logging.
     */
    protected function setupLogging()
    {
        // Custom logger
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/vend.log'),
            'categories' => ['angellco\vend\*'],
        ]);

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
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return Settings|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

}
