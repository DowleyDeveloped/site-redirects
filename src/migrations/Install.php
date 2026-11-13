<?php

namespace dowleydeveloped\siteredirects\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%fortytwo_redirects}}', [
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
            '{{%fortytwo_redirects}}',
            ['enabled', 'siteId', 'priority'],
            false
        );

        $this->createIndex(
            null,
            '{{%fortytwo_redirects}}',
            ['oldPath', 'siteId'],
            false
        );

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%fortytwo_redirects}}');
        return true;
    }
}
