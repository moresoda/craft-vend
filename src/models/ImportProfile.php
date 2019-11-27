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

use Craft;
use craft\base\Model;
use craft\helpers\Json;
use craft\validators\ArrayValidator;
use craft\validators\HandleValidator;
use craft\validators\UniqueValidator;

/**
 * ImportProfile model.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class ImportProfile extends Model
{
    // Properties
    // =========================================================================

    /**
     * @var int|null ID
     */
    public $id;

    /**
     * @var string|null Name
     */
    public $name;

    /**
     * @var string|null Handle
     */
    public $handle;

    /**
     * @var array Map
     */
    public $map;

    /**
     * @var string|null UID
     */
    public $uid;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'handle' => Craft::t('app', 'Handle'),
            'name' => Craft::t('app', 'Name'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = parent::rules();
        $rules[] = [['id'], 'number', 'integerOnly' => true];
        $rules[] = [['handle'], HandleValidator::class, 'reservedWords' => ['id', 'dateCreated', 'dateUpdated', 'uid', 'title']];
        $rules[] = [['name', 'handle'], UniqueValidator::class, 'targetClass' => __CLASS__];
        $rules[] = [['name', 'handle', 'map'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['map'], ArrayValidator::class];
        return $rules;
    }

    /**
     * Use the translated name as the string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return Craft::t('site', $this->name) ?: static::class;
    }
}