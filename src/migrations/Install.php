<?php

namespace dowleydeveloped\siteredirects\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%dowleydeveloped_redirects}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer(),
            'oldPath' => $this->string()->notNull(),
            'targetUrl' => $this->string()->notNull(),
            'statusCode' => $this->smallInteger()->notNull()->defaultValue(301),
            'matchType' => $this->string(20)->notNull()->defaultValue('exact'),
            'priority' => $this->integer()->notNull()->defaultValue(100),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(
            null,
            '{{%dowleydeveloped_redirects}}',
            ['enabled', 'siteId', 'priority'],
            false
        );

        $this->createIndex(
            null,
            '{{%dowleydeveloped_redirects}}',
            ['oldPath', 'siteId'],
            false
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%dowleydeveloped_redirects}}');
        return true;
    }
}
