<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\records;

use craft\db\ActiveRecord;

/**
 * Import Profile record.
 *
 * @property int $id ID
 * @property string $name Name
 * @property string $handle Handle
 * @property string $map Map
 * @property string $uid Uid
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ImportProfile extends ActiveRecord
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     * @return string
     */
    public static function tableName(): string
    {
        return '{{%vend_importprofiles}}';
    }
}