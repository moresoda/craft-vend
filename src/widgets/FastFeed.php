<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\widgets;

use angellco\vend\web\assets\widgets\WidgetsAsset;
use Craft;
use craft\base\Widget;

/**
 * Full Feed widget
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author    Angell & Co
 * @package   Vend
 * @since     2.2.0
 */
class FastFeed extends Widget
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('vend', 'Vend - Fast Sync');
    }

    /**
     * @inheritdoc
     */
    public static function icon()
    {
        return Craft::getAlias('@angellco/vend/icon-mask.svg');
    }

    /**
     * @inheritdoc
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function getBodyHtml()
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(WidgetsAsset::class);
        $view->registerJs('new Craft.Vend.FastFeedWidget({widgetId:'.$this->id.'});');
        return $view->renderTemplate('vend/widgets/feeds/fast/body');
    }

    /**
     * @inheritDoc
     */
    public static function maxColspan()
    {
        return 1;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritDoc
     */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

}