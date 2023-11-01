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

use angellco\vend\elements\actions\SyncProductWithVendAction;
use angellco\vend\models\Settings;
use angellco\vend\oauth\providers\VendVenveo as VendProvider;
use angellco\vend\queue\jobs\RegisterSale;
use angellco\vend\services\Api as VendApi;
use angellco\vend\services\ImportProfiles;
use angellco\vend\services\Orders;
use angellco\vend\services\Products;
use angellco\vend\services\ParkedSales;
use angellco\vend\web\assets\products\EditProductAsset;
use angellco\vend\widgets\FastFeed;
use angellco\vend\widgets\FullFeed;
use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\Field;
use craft\base\Plugin;
use craft\base\PreviewableFieldInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\events\LineItemEvent;
use craft\commerce\models\LineItem;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\feedme\events\FeedProcessEvent;
use craft\feedme\models\FeedModel;
use craft\feedme\Plugin as FeedMe;
use craft\feedme\queue\jobs\FeedImport;
use craft\feedme\services\Process;
use craft\helpers\Json;
use craft\helpers\Queue;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\log\FileTarget;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\UserPermissions;
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
 * @property Products       $products
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
    public $schemaVersion = '2.5.0';

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
    public function getCpNavItem()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if ($currentUser->can('vend:parked-sales') || $currentUser->can('vend:sync') || $currentUser->can('vend:settings')) {
            $ret = parent::getCpNavItem();
            $ret['label'] = Craft::t('vend', 'Vend');

            // Only show sub-navs the user has permission to view
            if ($currentUser->can('vend:sync')) {
                $ret['subnav']['sync'] = [
                    'label' => Craft::t('vend', 'Sync'),
                    'url' => 'vend/sync'
                ];
            }

            if ($currentUser->can('vend:parked-sales')) {
                $ret['subnav']['parked-sales'] = [
                    'label' => Craft::t('vend', 'Parked Sales'),
                    'url' => 'vend/parked-sales'
                ];
            }

            if ($currentUser->can('vend:settings') && Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
                $ret['subnav']['settings'] = [
                    'label' => Craft::t('app', 'Settings'),
                    'url' => 'vend/settings'
                ];
            }

            return $ret;
        }

        return null;
    }

    // Protected Methods
    // =========================================================================

    /**
     * Install our event listeners.
     */
    protected function installEventListeners()
    {
        // User permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function(RegisterUserPermissionsEvent $event) {
                $event->permissions['Vend'] = [
                    'vend:sync' => [
                        'label' => 'Sync',
                    ],
                    'vend:parked-sales' => [
                        'label' => 'Parked Sales',
                    ],
                    'vend:settings' => [
                        'label' => 'Settings',
                        'nested' => [
                            'vend:settings:import-profiles' => [
                                'label' => 'Import Profiles',
                            ],
                            'vend:settings:webhooks' => [
                                'label' => 'Webhooks',
                            ],
                            'vend:settings:feed-me' => [
                                'label' => 'Feed Me',
                            ],
                            'vend:settings:shipping' => [
                                'label' => 'Shipping',
                            ],
                            'vend:settings:tax' => [
                                'label' => 'Tax',
                            ],
                            'vend:settings:general' => [
                                'label' => 'General Settings',
                            ],
                        ]
                    ]
                ];
            }
        );

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
            static function(RegisterComponentTypesEvent $event) {
                $event->types[] = VendProvider::class;
            }
        );

        // Save the domain prefix into the plugin settings
        if (!Craft::$app->getRequest()->isConsoleRequest) {
            Event::on(
                Tokens::class,
                Tokens::EVENT_BEFORE_TOKEN_SAVED,
                static function(TokenEvent $e) {
                    $domainPrefix = Craft::$app->getRequest()->getQueryParam('domain_prefix');
                    if ($domainPrefix) {
                        Craft::$app->getPlugins()->savePluginSettings(self::$plugin, [
                            'domainPrefix' => $domainPrefix
                        ]);
                    }
                }
            );
        }

        // Bind to the order complete event, so we can register the sale with Vend
        if ($this->getSettings()->vend_registerSales) {
            Event::on(
                Order::class,
                Order::EVENT_AFTER_COMPLETE_ORDER,
                function(Event $event) {
                    /** @var Order $order */
                    $order = $event->sender;
                    Queue::push(new RegisterSale(['orderId' => $order->id]));
                }
            );
        }

        // Bind to element after save event so we can update stock of composite
        // products if need be
        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(Event $e) {
                /** @var Element $element */
                $element = $e->element;

                if (is_a($element, Variant::class)) {
                    $compositeParentProductIds = Json::decode($element->vendCompositeParentProductIds);
                    if (is_array($compositeParentProductIds)) {
                        foreach ($compositeParentProductIds as $compositeParentProductId) {
                            // Get the stock
                            $stock = $this->products->calculateInventoryForComposite($compositeParentProductId);

                            // Update the parent Entry
                            $this->products->updateInventoryForEntry($compositeParentProductId, $stock);

                            // Update the parent Variant
                            $this->products->updateInventoryForVariant($compositeParentProductId, $stock);
                        }
                    }
                }
            }
        );

        // Register sync action for Products
        Event::on(
            Product::class,
            Element::EVENT_REGISTER_ACTIONS,
            function(RegisterElementActionsEvent $event) {
                if (Craft::$app->getUser()->checkPermission('vend:sync')) {
                    $event->actions[] = SyncProductWithVendAction::class;
                }
            }
        );

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

                                $isVendOrder = false;
                                $vendOrderId = false;

                                // First check if we have an Vend Order ID - if we do, then its
                                // definitely a Vend order...
                                if ($vendOrderIdField instanceof PreviewableFieldInterface) {
                                    // Was this field value eager-loaded?
                                    if ($vendOrderIdField instanceof EagerLoadingFieldInterface && $order->hasEagerLoadedElements($vendOrderIdField->handle)) {
                                        $vendOrderId = $order->getEagerLoadedElements($vendOrderIdField->handle);
                                    } else {
                                        // The field might not actually belong to this element
                                        try {
                                            $vendOrderId = $order->getFieldValue($vendOrderIdField->handle);
                                        } catch (\Throwable $e) {
                                            $vendOrderId = $vendOrderIdField->normalizeValue(null);
                                        }
                                    }
                                }

                                // Check its a Vend Order from line item data if we have no Vend Order ID
                                if ($vendOrderId) {
                                    $isVendOrder = true;
                                } else {
                                    $firstLineItem = $order->getLineItems()[0];
                                    if (is_a($firstLineItem->getPurchasable(), Variant::class)) {
                                        $isVendOrder = true;
                                    }
                                }

                                if ($isVendOrder) {
                                    // If we have an ID, show the link
                                    if ($vendOrderId) {
                                        $e->html = "<a href='https://{$this->getSettings()->domainPrefix}.vendhq.com/redirect/1.0/sales/{$vendOrderId}?action=view' class='go' target='_blank'>View on Vend</a>";
                                    } else {
                                        // If we don’t, show an error
                                        $e->html = "<span class='error'>Not yet on Vend</span>";
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
            ->onAdd($this->importProfiles::CONFIG_PROFILES_KEY . '.{uid}', [$this->importProfiles, 'handleChangedProfile'])
            ->onUpdate($this->importProfiles::CONFIG_PROFILES_KEY . '.{uid}', [$this->importProfiles, 'handleChangedProfile'])
            ->onRemove($this->importProfiles::CONFIG_PROFILES_KEY . '.{uid}', [$this->importProfiles, 'handleDeletedProfile']);

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
        if ($this->getSettings()->cascadeFeedMe) {
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

                        // Trigger composites
                        $runQueue = false;
                        foreach ($feeds->getFeeds() as $feed) {
                            if (StringHelper::contains($feed->feedUrl, 'vend/products/composites')) {
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
                    } elseif (StringHelper::contains($currentFeed->feedUrl, 'vend/products/composites')) {

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
                $view->registerAssetBundle(EditProductAsset::class);
                $view->registerJs('new Craft.Vend.OrderEdit({commerceOrderId:"'.$order->id.'",vendOrderId:"'.$order->vendOrderId.'"});');
            }

        });

        // Load up our order edit stuff
        $view->hook('cp.commerce.product.edit.content', static function(array &$context) use($view) {
            /** @var Product $product */
            $product = $context['product'];

            $domainPrefix = Vend::$plugin->getSettings()->domainPrefix;

            $view->registerAssetBundle(EditProductAsset::class);
            $view->registerJs('new Craft.Vend.ProductEdit({domainPrefix:"'.$domainPrefix.'",vendProductId:"'.$product->vendProductId.'"});');

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

        // Custom logger
        Craft::getLogger()->dispatcher->targets[] = new FileTarget([
            'logFile' => Craft::getAlias('@storage/logs/vend-webhooks.log'),
            'categories' => ['vend\webhooks\*'],
            'logVars' => [],
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
