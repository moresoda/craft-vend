<?php
/**
 * Vend plugin for Craft Commerce
 *
 * Connect your Craft Commerce store to Vend POS.
 *
 * @link      https://angell.io
 * @copyright Copyright (c) 2019 Angell & Co
 */

namespace angellco\vend\migrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\elements\Entry;
use craft\errors\EntryTypeNotFoundException;
use craft\errors\SectionNotFoundException;
use craft\errors\SiteNotFoundException;
use craft\fields\Date;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\models\FieldGroup;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\records\FieldLayout;
use craft\records\FieldLayoutField;
use craft\records\FieldLayoutTab;
use Throwable;

/**
 * Vend install migration.
 *
 * @author    Angell & Co
 * @package   Vend
 * @since     2.0.0
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @return bool
     * @throws EntryTypeNotFoundException
     * @throws SectionNotFoundException
     * @throws SiteNotFoundException
     * @throws Throwable
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->createIndexes();
            $this->addForeignKeys();
            $this->insertDefaultData();
        }
        return true;
    }

    /**
     * @return bool
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables(): bool
    {
        $tablesCreated = false;

        // vend_importprofiles table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%vend_importprofiles}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%vend_importprofiles}}',
                [
                    'id' => $this->primaryKey(),
                    'name' => $this->string()->notNull(),
                    'handle' => $this->string()->notNull(),
                    'map' => $this->text(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        // vend_parkedsales table
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%vend_parkedsales}}');
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                '{{%vend_parkedsales}}',
                [
                    'id' => $this->primaryKey(),
                    'orderId' => $this->integer()->notNull(),
                    'retryAfter' => $this->dateTime(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes()
    {
        // vend_importprofiles table
        $this->createIndex(null, '{{%vend_importprofiles}}', 'name', true);
        $this->createIndex(null, '{{%vend_importprofiles}}', 'handle', true);

        // vend_parkedsales table
        $this->createIndex(null, '{{%vend_parkedsales}}', 'orderId', false);
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
        // vend_parkedsales table
        $this->addForeignKey(null, '{{%vend_parkedsales}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', 'CASCADE');
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        // vend_importprofiles table
        $this->dropTableIfExists('{{%vend_importprofiles}}');

        // vend_parkedsales table
        $this->dropTableIfExists('{{%vend_parkedsales}}');
    }

    /**
     * @throws EntryTypeNotFoundException
     * @throws SectionNotFoundException
     * @throws SiteNotFoundException
     * @throws Throwable
     */
    protected function insertDefaultData()
    {
        $this->_createVendProductsSection();
    }

    /**
     * Creates the Vend Products Section and other bits
     *
     * @throws Throwable
     * @throws EntryTypeNotFoundException
     * @throws SectionNotFoundException
     * @throws SiteNotFoundException
     */
    private function _createVendProductsSection()
    {
        $defaultSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        // Create Products Section
        $section = new Section([
            'name' => 'Vend Products',
            'handle' => 'vendProducts',
            'type' => 'channel',
            'enableVersioning' => false,
            'propagationMethod' => 'all',
        ]);

        $section->setSiteSettings([
            new Section_SiteSettings([
                'siteId' => $defaultSiteId,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null
            ])
        ]);

        // Save it
        if (Craft::$app->getSections()->saveSection($section)) {
            $entryType = $section->getEntryTypes()[0];

            $fieldsService = Craft::$app->getFields();

            // Create a field group
            $group = new FieldGroup(['name' => 'Vend']);
            $fieldsService->saveGroup($group);

            // Create the fields
            $productIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product ID',
                'handle' => 'vendProductId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $typeIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Type ID',
                'handle' => 'vendProductTypeId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $brandIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Brand ID',
                'handle' => 'vendProductBrandId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $supplierIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Supplier ID',
                'handle' => 'vendProductSupplierId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $tagIdsField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Tag IDs',
                'handle' => 'vendProductTagIds',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $hasVariantsField = $fieldsService->createField([
                'type' => Lightswitch::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Has Variants',
                'handle' => 'vendProductHasVariants',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $isVariantField = $fieldsService->createField([
                'type' => Lightswitch::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Is Variant',
                'handle' => 'vendProductIsVariant',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $variantParentIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Variant Parent ID',
                'handle' => 'vendProductVariantParentId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $variantNameField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Variant Name',
                'handle' => 'vendProductVariantName',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $variantInventoryField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Inventory Count',
                'handle' => 'vendInventoryCount',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $jsonField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product JSON',
                'handle' => 'vendProductJson',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [
                    'code' => true,
                    'multiline' => true,
                ],
            ]);

            $compositesField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Product Composites',
                'handle' => 'vendProductComposites',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [
                    'code' => true,
                    'multiline' => true,
                ],
            ]);

            $customerIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Customer Id',
                'handle' => 'vendCustomerId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $orderIdField = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $group->id,
                'name' => 'Vend Order ID',
                'handle' => 'vendOrderId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $dateUpdatedField = $fieldsService->createField([
                'type' => Date::class,
                'groupId' => $group->id,
                'name' => 'Vend Date Updated',
                'handle' => 'vendDateUpdated',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'showDate' => true,
                'showTime' => true,
                'settings' => [],
            ]);

            $dateCreatedField = $fieldsService->createField([
                'type' => Date::class,
                'groupId' => $group->id,
                'name' => 'Vend Date Created',
                'handle' => 'vendDateCreated',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'showDate' => true,
                'showTime' => true,
                'settings' => [],
            ]);

            // Save all the fields
            if (
                $fieldsService->saveField($productIdField)
                && $fieldsService->saveField($typeIdField)
                && $fieldsService->saveField($brandIdField)
                && $fieldsService->saveField($supplierIdField)
                && $fieldsService->saveField($tagIdsField)
                && $fieldsService->saveField($hasVariantsField)
                && $fieldsService->saveField($isVariantField)
                && $fieldsService->saveField($variantParentIdField)
                && $fieldsService->saveField($variantNameField)
                && $fieldsService->saveField($variantInventoryField)
                && $fieldsService->saveField($jsonField)
                && $fieldsService->saveField($compositesField)
                && $fieldsService->saveField($customerIdField)
                && $fieldsService->saveField($orderIdField)
                && $fieldsService->saveField($dateUpdatedField)
                && $fieldsService->saveField($dateCreatedField)
            ) {

                // Product Entries Field Layout
                $this->insert(FieldLayout::tableName(), ['type' => Entry::class]);
                $fieldLayoutId = $this->db->getLastInsertID(FieldLayout::tableName());

                $this->insert(FieldLayoutTab::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'sortOrder' => 0,
                    'name' => 'Product Details'
                ]);
                $tabId = $this->db->getLastInsertID(FieldLayoutTab::tableName());

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $productIdField->id,
                    'required' => true,
                    'sortOrder' => 0
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $dateUpdatedField->id,
                    'required' => false,
                    'sortOrder' => 1
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $dateCreatedField->id,
                    'required' => false,
                    'sortOrder' => 2
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $typeIdField->id,
                    'required' => false,
                    'sortOrder' => 3
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $brandIdField->id,
                    'required' => false,
                    'sortOrder' => 4
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $supplierIdField->id,
                    'required' => false,
                    'sortOrder' => 5
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $tagIdsField->id,
                    'required' => false,
                    'sortOrder' => 6
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $hasVariantsField->id,
                    'required' => false,
                    'sortOrder' => 7
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $isVariantField->id,
                    'required' => false,
                    'sortOrder' => 8
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $variantParentIdField->id,
                    'required' => false,
                    'sortOrder' => 9
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $variantNameField->id,
                    'required' => false,
                    'sortOrder' => 10
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $variantInventoryField->id,
                    'required' => false,
                    'sortOrder' => 11
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $jsonField->id,
                    'required' => true,
                    'sortOrder' => 12
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $compositesField->id,
                    'required' => true,
                    'sortOrder' => 13
                ]);

                $fieldLayout = $fieldsService->getLayoutById($fieldLayoutId);

                if ($fieldLayout) {
                    $entryType->setFieldLayout($fieldLayout);
                    Craft::$app->getSections()->saveEntryType($entryType);
                }
            }

        }

    }
}