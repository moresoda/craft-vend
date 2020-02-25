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
     * @var bool
     */
    public $vend_registerSales = false;

    /**
     * @var string
     */
    public $vend_customerGroupId;

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

    /**
     * @var string
     */
    public $vend_discountProductId;

    /**
     * @var string
     */
    public $vend_noTaxId;

    // Vend / Commerce relations
    // =========================================================================

    /**
     * @var mixed
     */
    public $taxMap;

    /**
     * @var mixed
     */
    public $shippingMap;

    // Commerce bits
    // =========================================================================

    /**
     * @var string
     */
    public $commerce_parkedSaleEmailId;
}