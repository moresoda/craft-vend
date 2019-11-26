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

use angellco\vend\Vend;

use Craft;
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


    // Commerce
    // =========================================================================

    /**
     * @var bool
     */
    public $commerce_promotable = true;

    /**
     * @var array
     */
    public $commerce_taxCategoryMap;

    /**
     * @var int
     */
    public $commerce_defaultTaxCategoryId;

    /**
     * @var int
     */
    public $commerce_defaultProductTypeId;

    /**
     * @var string
     */
    public $commerce_excludeVendTypes;

    /**
     * @var array
     */
    public $commerce_includeVendTypes;

    /**
     * @var bool
     */
    public $commerce_registerSales = false;

    /**
     * @var int
     */
    public $commerce_parkedSaleEmailId;


    // Vend basics
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


    // Vend shipping
    // =========================================================================

    /**
     * @var array
     */
    public $shippingMap;


    // Vend product fields
    // =========================================================================

    /**
     * @var string
     */
    public $productVend_id;

    /**
     * @var string
     */
    public $productVend_name;

    /**
     * @var string
     */
    public $productVend_base_name;

    /**
     * @var string
     */
    public $productVend_handle;

    /**
     * @var string
     */
    public $productVend_type;

    /**
     * @var string
     */
    public $productVend_description;


    // Vend variant fields
    // =========================================================================

    /**
     * @var string
     */
    public $variantVend_id;

    /**
     * @var string
     */
    public $variantVend_name;

    /**
     * @var string
     */
    public $variantVend_base_name;

    /**
     * @var string
     */
    public $variantVend_handle;

    /**
     * @var string
     */
    public $variantVend_type;

    /**
     * @var string
     */
    public $variantVend_description;

    /**
     * @var string
     */
    public $variantVend_variant_parent_id;

    /**
     * @var string
     */
    public $variantVend_variant_option_one_name;

    /**
     * @var string
     */
    public $variantVend_variant_option_one_value;

    /**
     * @var string
     */
    public $variantVend_variant_option_two_name;

    /**
     * @var string
     */
    public $variantVend_variant_option_two_value;

    /**
     * @var string
     */
    public $variantVend_variant_option_three_name;

    /**
     * @var string
     */
    public $variantVend_variant_option_three_value;

}