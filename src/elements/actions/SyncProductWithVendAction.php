<?php

namespace angellco\vend\elements\actions;

use angellco\vend\queue\jobs\UpdateProduct;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Queue;

class SyncProductWithVendAction extends ElementAction
{
    public function getTriggerLabel(): string
    {
        return Craft::t('vend', 'Sync with Vend');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $products = $query->all();

        foreach ($products as $product) {
            Queue::push(new UpdateProduct(['product_id' => $product->vendProductId]));
        }

        return true;
    }
}