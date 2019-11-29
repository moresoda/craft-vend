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
use craft\elements\db\ElementQueryInterface;
use craft\helpers\Json;
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
     * @var string|array Map
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

    /**
     * @return array|mixed|string
     */
    public function getMap()
    {
        if (is_array($this->map)) {
            return $this->map;
        }

        return Json::decode($this->map);
    }

    /**
     * @param array|string $map
     */
    public function setMap($map): void
    {
        if (is_array($map)) {
            $map = Json::encode($map);
        }

        $this->map = $map;
    }

    /**
     * Applies the profile map to the given query object.
     *
     * @param ElementQueryInterface $query
     *
     * @return ElementQueryInterface
     */
    public function apply(ElementQueryInterface $query)
    {
        $map = $this->getMap();

        $fieldColumnPrefix = Craft::$app->getContent()->fieldColumnPrefix;

        // Product Types
        if ($map['productTypes']['included']) {
            $query->andWhere([
                'in',
                'content.'.$fieldColumnPrefix.'vendProductTypeId',
                array_values($map['productTypes']['included'])
            ]);
        }

        if ($map['productTypes']['excluded']) {
            $query->andWhere([
                'not in',
                $fieldColumnPrefix.'vendProductTypeId',
                array_values($map['productTypes']['excluded'])
            ]);
        }

        // Brands
        if ($map['brands']['included']) {
            $query->andWhere([
                'in',
                $fieldColumnPrefix.'vendProductBrandId',
                array_values($map['brands']['included'])
            ]);
        }

        if ($map['brands']['excluded']) {
            $query->andWhere([
                'not in',
                $fieldColumnPrefix.'vendProductBrandId',
                array_values($map['brands']['excluded'])
            ]);
        }

        // Suppliers
        if ($map['suppliers']['included']) {
            $query->andWhere([
                'in',
                $fieldColumnPrefix.'vendProductSupplierId',
                array_values($map['suppliers']['included'])
            ]);
        }

        if ($map['suppliers']['excluded']) {
            $query->andWhere([
                'not in',
                $fieldColumnPrefix.'vendProductSupplierId',
                array_values($map['suppliers']['excluded'])
            ]);
        }

        return $query;
    }
}