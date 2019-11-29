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
    protected function createTables()
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

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function createIndexes(): void
    {
        // vend_importprofiles table
        $this->createIndex(null, '{{%vend_importprofiles}}', 'name', true);
        $this->createIndex(null, '{{%vend_importprofiles}}', 'handle', true);
    }

    /**
     * @return void
     */
    protected function removeTables(): void
    {
        // vend_importprofiles table
        $this->dropTableIfExists('{{%vend_importprofiles}}');
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
     * Creates the Vend Products Section
     *
     * @throws Throwable
     * @throws EntryTypeNotFoundException
     * @throws SectionNotFoundException
     * @throws SiteNotFoundException
     */
    private function _createVendProductsSection(): void
    {
        $defaultSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        // Section
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

        if (Craft::$app->getSections()->saveSection($section)) {
            $entryType = $section->getEntryTypes()[0];

            $fieldsService = Craft::$app->getFields();

            // Create a field group
            $group = new FieldGroup(['name' => 'Vend']);
            $fieldsService->saveGroup($group);

            // Create the fields
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

            if (
                $fieldsService->saveField($typeIdField)
                && $fieldsService->saveField($brandIdField)
                && $fieldsService->saveField($supplierIdField)
                && $fieldsService->saveField($jsonField)
            ) {

                // Field layout
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
                    'fieldId' => $typeIdField->id,
                    'required' => true,
                    'sortOrder' => 0
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $brandIdField->id,
                    'required' => false,
                    'sortOrder' => 1
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $supplierIdField->id,
                    'required' => false,
                    'sortOrder' => 2
                ]);

                $this->insert(FieldLayoutField::tableName(), [
                    'layoutId' => $fieldLayoutId,
                    'tabId' => $tabId,
                    'fieldId' => $jsonField->id,
                    'required' => true,
                    'sortOrder' => 3
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