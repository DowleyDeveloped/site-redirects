<?php

namespace dowleydeveloped\siteredirects;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use craft\web\UrlManager;

use dowleydeveloped\siteredirects\services\Imports;
use dowleydeveloped\siteredirects\services\Redirects;

use yii\base\Event;
use yii\web\Response;

class SiteRedirects extends Plugin
{
    public static SiteRedirects $plugin;
    public bool $hasCpSection = true;

	// Public Methods
	// =========================================================================

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

		// Create Custom Alias
		Craft::setAlias('@siteredirects', __DIR__);

        $this->setComponents([
            'redirects' => Redirects::class,
            'imports' => Imports::class,
        ]);

		// Events
		$this->_registerCpRules();
		$this->_checkRequest();
		$this->_elementSave();
		$this->_updateSlugs();
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
		$item = array_merge($item, [
			"icon" => "@siteredirects/icons/navigation.svg",
		]);

        $item['subnav'] = [
            'redirects' => [
                'label' => 'Redirects',
                'url' => 'site-redirects',
            ],
            'import' => [
                'label' => 'Import CSV',
                'url' => 'site-redirects/import',
            ],
        ];

        return $item;
    }

	// Private Methods
	// =====================================================================

    private function isRedirectLoop(string $currentUrl, string $targetUrl): bool
    {
        $currentPath = trim((string)parse_url($currentUrl, PHP_URL_PATH), '/');
        $targetPath = trim((string)parse_url($targetUrl, PHP_URL_PATH), '/');

        return $currentPath !== '' && $currentPath === $targetPath;
    }

	/**
	* @title: Register CP Rules
	* @desc: Register the CP urls for each page needed by the plugin.
	*/
	private function _registerCpRules()
	{
		Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['site-redirects'] = 'site-redirects/redirects/index';
                $event->rules['site-redirects/new'] = 'site-redirects/redirects/edit';
                $event->rules['site-redirects/<id:\\d+>'] = 'site-redirects/redirects/edit';
                $event->rules['site-redirects/import'] = 'site-redirects/imports/index';
            }
        );
	}

	/**
	* @title: Check Redirect
	* @desc: Before Craft handles any request, check if this URL should be redirected. If yes, redirect immediately and stop everything else.
	*/
	private function _checkRequest()
	{
		Event::on(
            Response::class,
			Response::EVENT_BEFORE_SEND,
			function(Event $event) {
				/** @var Response $response */
				$response = $event->sender;
			
				if ($response->getStatusCode() !== 404) {
					return;
				}
				
                $request = Craft::$app->getRequest();

                if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
                    return;
                }

                $path = trim($request->getPathInfo(), '/');
                $site = Craft::$app->getSites()->getCurrentSite();
                $siteId = $site?->id;

                $result = $this->redirects->match($path, $siteId);

				if (!$result) {
					return;
				}

				[$match, $matches] = $result;

				$targetUrl = $match->targetUrl;

				// Apply regex / wildcard replacements
				foreach ($matches as $i => $value) {
					$targetUrl = str_replace('$' . $i, $value, $targetUrl);
				}

				if (!$targetUrl) {
					return;
				}

				if ($this->isRedirectLoop($request->getAbsoluteUrl(), $targetUrl)) {
					return;
				}

				// Replace the error body and let the current send cycle finish.
				$response->data = null;
				$response->content = null;
				$response->stream = null;
				$response->format = Response::FORMAT_RAW;
				$response->getHeaders()->remove('Content-Length');
				$response->redirect($targetUrl, $match->statusCode);
            }
        );
	}

	/**
	* @title: Element Save
	* @desc: Events that need to be fired on Element Before/After Save
	*/
	private function _elementSave()
	{
		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_SAVE_ELEMENT,
			function(ElementEvent $event) {
				$this->redirects->prepareRedirectOnElementChange($event);
			}
		);

		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_SAVE_ELEMENT,
			function(ElementEvent $event) {
				$this->redirects->insertRedirectOnElementChange($event);
			}
		);
	}

	/**
	* @title: Update Slugs
	* @desc: Events that need to be fired on when a slug is changed
	*/
	private function _updateSlugs()
	{
		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_UPDATE_SLUG_AND_URI,
			function(ElementEvent $event) {
				$this->redirects->prepareRedirectOnElementChange($event);
			}
		);

		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_UPDATE_SLUG_AND_URI,
			function(ElementEvent $event) {
				$this->redirects->insertRedirectOnElementChange($event);
			}
		);
	}
}
