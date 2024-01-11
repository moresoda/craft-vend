<?php

namespace angellco\vend\errors;

use yii\base\Exception;

class ProductUpdatesLockedException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Product updates locked.';
    }
}