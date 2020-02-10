<?php

namespace angellco\vend\migrations;

use Craft;
use craft\db\Migration;

/**
 * m200210_110644_AddParkedSalesTable migration.
 */
class m200210_110644_AddParkedSalesTable extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableSchema = Craft::$app->db->schema->getTableSchema('{{%vend_parkedsales}}');
        if ($tableSchema === null) {
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
            $this->createIndex(null, '{{%vend_parkedsales}}', 'orderId', false);
            $this->addForeignKey(null, '{{%vend_parkedsales}}', 'orderId', '{{%commerce_orders}}', 'id', 'CASCADE', 'CASCADE');
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200210_110644_AddParkedSalesTable cannot be reverted.\n";
        return false;
    }
}
