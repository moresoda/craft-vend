<?php

namespace angellco\vend\migrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Date;
use craft\fields\PlainText;

/**
 * m200327_115640_AddVendDateUpdatedField migration.
 */
class m200327_115640_AddVendDateUpdatedField extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fieldsService = Craft::$app->getFields();

        if (!$fieldsService->getFieldByHandle('vendDateUpdated')) {
            $fieldGroupId = (new Query())
                ->select([
                    'id',
                    'name',
                ])
                ->where(['name' => 'Vend'])
                ->from([Table::FIELDGROUPS])
                ->scalar();

            $field = $fieldsService->createField([
                'type' => Date::class,
                'groupId' => $fieldGroupId,
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

            $fieldsService->saveField($field);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200327_115640_AddVendDateUpdatedField cannot be reverted.\n";
        return false;
    }
}
