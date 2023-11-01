<?php

namespace angellco\vend\web\assets\products;

use craft\commerce\web\assets\commercecp\CommerceCpAsset;
use craft\web\AssetBundle;

class EditProductAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CommerceCpAsset::class,
        ];

        $this->js = [
            'js/VendProductEdit.js',
        ];

        parent::init();
    }
}