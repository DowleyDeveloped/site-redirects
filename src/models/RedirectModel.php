<?php

namespace dowleydeveloped\siteredirects\models;

use craft\base\Model;

class RedirectModel extends Model
{
    public ?int $id = null;
    public ?int $siteId = null;
    public string $oldPath = '';
    public string $targetUrl = '';
    public int $statusCode = 301;
    public string $matchType = 'exact';
    public int $priority = 100;
    public bool $enabled = true;

    public function rules(): array
    {
        return [
            [['oldPath', 'targetUrl', 'matchType'], 'required'],
            [['siteId', 'priority'], 'integer'],
            [['enabled'], 'boolean'],
            [['statusCode'], 'in', 'range' => [301, 302]],
            [['matchType'], 'in', 'range' => ['exact', 'wildcard', 'regex']],
        ];
    }
}
