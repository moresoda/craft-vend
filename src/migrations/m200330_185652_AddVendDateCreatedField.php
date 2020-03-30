<?php

namespace angellco\vend\migrations;

use Craft;
use craft\base\Field;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\fields\Date;

/**
 * m200330_185652_AddVendDateCreatedField migration.
 */
class m200330_185652_AddVendDateCreatedField extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $fieldsService = Craft::$app->getFields();

        if (!$fieldsService->getFieldByHandle('vendDateCreated')) {
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

            $fieldsService->saveField($field);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200330_185652_AddVendDateCreatedField cannot be reverted.\n";
        return false;
    }
}
