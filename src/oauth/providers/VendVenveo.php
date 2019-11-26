<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\oauth\providers;

use angellco\vend\oauth\providers\Vend;
use venveo\oauthclient\base\Provider;

/**
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class VendVenveo extends Provider
{

    // Public Methods
    // =========================================================================

    /**
     * Returns the display name of this class.
     *
     * @return string The display name of this class.
     */
    public static function displayName(): string
    {
        return 'Vend';
    }

    /**
     * Get the class name for the League provider
     *
     * @return string
     */
    public static function getProviderClass(): string
    {
        return Vend::class;
    }
}