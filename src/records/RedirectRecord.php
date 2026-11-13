<?php

namespace dowleydeveloped\siteredirects\records;

use craft\db\ActiveRecord;

class RedirectRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%fortytwo_redirects}}';
    }
}
