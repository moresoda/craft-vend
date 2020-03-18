<?php

namespace angellco\vend\migrations;

use Craft;
use craft\base\Field;
use craft\commerce\Plugin;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\PlainText;

/**
 * m200318_220208_AddVendOrderIdField migration.
 */
class m200318_220208_AddVendOrderIdField extends Migration
{
    /**
     * @inheritdoc
     *
     * @return bool|void
     * @throws \Throwable
     */
    public function safeUp()
    {
        $fieldsService = Craft::$app->getFields();

        if (!$fieldsService->getFieldByHandle('vendOrderId')) {
            $fieldGroupId = (new Query())
                ->select([
                    'id',
                    'name',
                ])
                ->where(['name' => 'Vend'])
                ->from([Table::FIELDGROUPS])
                ->scalar();

            $field = $fieldsService->createField([
                'type' => PlainText::class,
                'groupId' => $fieldGroupId,
                'name' => 'Vend Order ID',
                'handle' => 'vendOrderId',
                'instructions' => '',
                'searchable' => true,
                'translationMethod' => Field::TRANSLATION_METHOD_NONE,
                'translationKeyFormat' => '',
                'settings' => [],
            ]);

            $fieldsService->saveField($field);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200318_220208_AddVendOrderIdField cannot be reverted.\n";
        return false;
    }
}
