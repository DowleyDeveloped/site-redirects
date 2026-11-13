<?php

namespace dowleydeveloped\siteredirects\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\events\ElementEvent;

use dowleydeveloped\siteredirects\SiteRedirects;
use dowleydeveloped\siteredirects\models\RedirectModel;
use dowleydeveloped\siteredirects\records\RedirectRecord;

use yii\caching\TagDependency;

class Redirects extends Component
{
    public const CACHE_TAG = 'site-redirects';

    public function getAll(): array
    {
        return RedirectRecord::find()
            ->orderBy([
                'priority' => SORT_DESC,
                'oldPath' => SORT_ASC,
            ])
            ->all();
    }

    public function getById(int $id): ?RedirectRecord
    {
        return RedirectRecord::findOne($id);
    }

    public function match(string $path, ?int $siteId = null): ?array
    {
        $path = trim($path, '/');
        $cacheKey = [
            'site-redirects.match',
            'siteId' => $siteId,
            'path' => $path,
        ];

        return Craft::$app->getCache()->getOrSet(
            $cacheKey,
            fn() => $this->findMatch($path, $siteId),
            null,
            new TagDependency(['tags' => [self::CACHE_TAG]])
        );
    }

    public function saveRedirect(RedirectModel $model): bool
    {
        if (!$model->validate()) {
            return false;
        }

        $record = $model->id
            ? RedirectRecord::findOne($model->id)
            : new RedirectRecord();

        if (!$record) {
            return false;
        }

        $record->siteId = $model->siteId ?: null;
        $record->oldPath = trim($model->oldPath, '/');
        $record->targetUrl = trim($model->targetUrl);
        $record->statusCode = $model->statusCode;
        $record->matchType = $model->matchType;
        $record->priority = $model->priority;
        $record->enabled = $model->enabled;

        $saved = (bool)$record->save();

        if ($saved) {
            $this->invalidateCache();
        }

        return $saved;
    }

    public function deleteRedirectById(int $id): bool
    {
        $record = RedirectRecord::findOne($id);

        if (!$record) {
            return false;
        }

        $deleted = (bool)$record->delete();

        if ($deleted) {
            $this->invalidateCache();
        }

        return $deleted;
    }

    public function toModel(?RedirectRecord $record = null): RedirectModel
    {
        return new RedirectModel([
            'id' => $record?->id,
            'siteId' => $record?->siteId,
            'oldPath' => $record?->oldPath ?? '',
            'targetUrl' => $record?->targetUrl ?? '',
            'statusCode' => $record?->statusCode ?? 301,
            'matchType' => $record?->matchType ?? 'exact',
            'priority' => $record?->priority ?? 100,
            'enabled' => (bool)($record?->enabled ?? true),
        ]);
    }

    public function invalidateCache(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), [self::CACHE_TAG]);
    }

	public function prepareRedirectOnElementChange(ElementEvent $event)
	{
		$element = $event->element;

		// Only care about entries (or expand later)
		if (!$element instanceof Entry) {
			return;
		}

		// Skip drafts/revisions
		if ($element->getIsDraft() || $element->getIsRevision()) {
			return;
		}

		// If new element, nothing to redirect
		if (!$element->id) {
			return;
		}

		// Get the existing element from DB
		$original = Craft::$app->getElements()->getElementById(
			$element->id,
			$element::class,
			$element->siteId
		);

		if (!$original) {
			return;
		}

		$oldUri = $original->uri;
		$newUri = $element->uri;

		// Store ONLY if it might change
		if ($oldUri && $newUri && $oldUri !== $newUri) {
			$this->_pendingRedirects[$element->id . ':' . $element->siteId] = [
				'oldUri' => $oldUri,
				'newUri' => $newUri,
				'siteId' => $element->siteId,
			];
		}
	}

	public function insertRedirectOnElementChange(ElementEvent $event)
	{
		$element = $event->element;

		if (!$element instanceof Entry) {
			return;
		}

		if ($element->getIsDraft() || $element->getIsRevision()) {
			return;
		}

		$key = $element->id . ':' . $element->siteId;

		if (!isset($this->_pendingRedirects[$key])) {
			return;
		}

		$data = $this->_pendingRedirects[$key];
		unset($this->_pendingRedirects[$key]);

		$oldUri = $data['oldUri'];
		$newUri = $data['newUri'];

		if ($oldUri === '__home__' || $newUri === '__home__') {
			return;
		}

		$oldPath = trim($oldUri, '/');
		$newPath = trim($newUri, '/');

		if (!$oldPath || !$newPath || $oldPath === $newPath) {
			return;
		}

		$existing = RedirectRecord::find()
			->where([
				'oldPath' => $oldPath,
				'siteId' => $data['siteId'],
			])
			->one();

		if ($existing) {
			return;
		}

		$model = new RedirectModel([
			'siteId' => $data['siteId'],
			'oldPath' => $oldPath,
			'targetUrl' => '/' . $newPath,
			'statusCode' => 301,
			'matchType' => 'exact',
			'priority' => 5,
			'enabled' => true,
		]);

		$this->saveRedirect($model);
	}

	private array $_pendingRedirects = [];

    private function findMatch(string $path, ?int $siteId = null): ?array
    {
        $records = RedirectRecord::find()
            ->where(['enabled' => true])
            ->andWhere([
                'or',
                ['siteId' => $siteId],
                ['siteId' => null],
            ])
            ->orderBy([
                'priority' => SORT_DESC,
                'siteId' => SORT_DESC,
                'id' => SORT_ASC,
            ])
            ->all();

        foreach ($records as $record) {
			$matches = $this->matchesRecord($record, $path);

			if ($matches !== null) {
				return [$record, $matches];
			}
		}

        return null;
    }

    private function matchesRecord(RedirectRecord $record, string $path): ?array
	{
		$rule = trim((string)$record->oldPath, '/');

		return match ($record->matchType) {
			'exact' => $path === $rule ? [] : null,
			'wildcard' => $this->matchesWildcard($rule, $path),
			'regex' => $this->matchesRegex($rule, $path),
			default => null,
		};
	}

    private function matchesWildcard(string $rule, string $path): ?array
	{
		$quoted = preg_quote($rule, '/');
		$pattern = '/^' . str_replace('\*', '(.*)', $quoted) . '$/u';
	
		return preg_match($pattern, $path, $matches) === 1
			? $matches
			: null;
	}

    private function matchesRegex(string $rule, string $path): ?array
	{
		$pattern = $this->normalizeRegex($rule);

		if ($pattern === null) {
			return null;
		}

		if (preg_match($pattern, $path, $matches)) {
			return $matches;
		}

		return null;
	}

    private function normalizeRegex(string $rule): ?string
	{
		if ($rule === '') {
			return null;
		}

		// Escape delimiter automatically
		$rule = str_replace('/', '\/', $rule);

		return '/^' . $rule . '$/u';
	}
}
