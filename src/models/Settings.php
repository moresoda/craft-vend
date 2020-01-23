<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\models;

use craft\base\Model;

/**
 * Settings Model
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Settings extends Model
{
    // OAuth
    // =========================================================================

    /**
     * @var string
     */
    public $domainPrefix;

    // Vend necessities
    // =========================================================================

    /**
     * @var string
     */
    public $vend_userId;

    /**
     * @var string
     */
    public $vend_outletId;

    /**
     * @var string
     */
    public $vend_registerId;

    /**
     * @var string
     */
    public $vend_retailerPaymentTypeId;
}