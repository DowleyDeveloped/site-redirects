<?php

namespace dowleydeveloped\siteredirects\services;

use Craft;
use craft\base\Component;

use dowleydeveloped\siteredirects\SiteRedirects;
use dowleydeveloped\siteredirects\models\RedirectModel;
use dowleydeveloped\siteredirects\records\RedirectRecord;

class Imports extends Component
{
    public function importCsv(string $filePath, bool $dryRun = false): array
    {
        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if (!is_file($filePath)) {
            $results['errors'][] = 'CSV file could not be found.';
            return $results;
        }

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            $results['errors'][] = 'CSV file could not be opened.';
            return $results;
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            $results['errors'][] = 'CSV file is empty.';
            return $results;
        }

        $headers = array_map('trim', $headers);
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;

            if (!$row || count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
                $results['skipped']++;
                continue;
            }

            $data = array_combine($headers, $row);

            if ($data === false) {
                $results['errors'][] = "Line {$line}: column mismatch.";
                continue;
            }

            $oldPath = trim((string)($data['oldPath'] ?? ''), '/');
            $targetUrl = trim((string)($data['targetUrl'] ?? ''));
            $statusCode = (int)($data['statusCode'] ?? 301);
            $matchType = trim((string)($data['matchType'] ?? 'exact'));
            $priority = (int)($data['priority'] ?? 100);
            $enabledRaw = $data['enabled'] ?? '1';
            $enabled = in_array(strtolower((string)$enabledRaw), ['1', 'true', 'yes', 'y'], true);

            $siteId = null;
            if (($data['siteId'] ?? '') !== '') {
                $siteId = (int)$data['siteId'];
            }

            if ($oldPath === '' || $targetUrl === '') {
                $results['errors'][] = "Line {$line}: oldPath and targetUrl are required.";
                continue;
            }

            $existing = RedirectRecord::find()
                ->where([
                    'oldPath' => $oldPath,
                    'siteId' => $siteId,
                ])
                ->one();

            $model = new RedirectModel([
                'id' => $existing?->id,
                'siteId' => $siteId,
                'oldPath' => $oldPath,
                'targetUrl' => $targetUrl,
                'statusCode' => $statusCode,
                'matchType' => $matchType,
                'priority' => $priority,
                'enabled' => $enabled,
            ]);

            if (!$model->validate()) {
                $results['errors'][] = "Line {$line}: validation failed.";
                continue;
            }

            if ($dryRun) {
                $existing ? $results['updated']++ : $results['imported']++;
                continue;
            }

            $saved = SiteRedirects::$plugin->redirects->saveRedirect($model);

            if (!$saved) {
                $results['errors'][] = "Line {$line}: failed saving redirect.";
                continue;
            }

            $existing ? $results['updated']++ : $results['imported']++;
        }

        fclose($handle);

        SiteRedirects::$plugin->redirects->invalidateCache();

        return $results;
    }
}
