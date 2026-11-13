<?php

namespace dowleydeveloped\siteredirects\controllers;

use Craft;
use craft\web\Controller;
use dowleydeveloped\siteredirects\models\RedirectModel;
use dowleydeveloped\siteredirects\SiteRedirects;
use yii\web\Response;

class RedirectsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionIndex(): Response
    {
        return $this->renderTemplate('site-redirects/redirects/index', [
            'redirects' => SiteRedirects::$plugin->redirects->getAll(),
        ]);
    }

    public function actionEdit(?int $id = null, ?RedirectModel $redirect = null): Response
    {
        if ($redirect === null) {
            $record = $id ? SiteRedirects::$plugin->redirects->getById($id) : null;
            $redirect = SiteRedirects::$plugin->redirects->toModel($record);
        }

        return $this->renderTemplate('site-redirects/redirects/_edit', [
            'redirect' => $redirect,
            'sites' => Craft::$app->getSites()->getAllSites(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $redirect = new RedirectModel([
            'id' => $this->request->getBodyParam('id') ?: null,
            'siteId' => $this->request->getBodyParam('siteId') ?: null,
            'oldPath' => $this->request->getBodyParam('oldPath', ''),
            'targetUrl' => $this->request->getBodyParam('targetUrl', ''),
            'statusCode' => (int)$this->request->getBodyParam('statusCode', 301),
            'matchType' => $this->request->getBodyParam('matchType', 'exact'),
            'priority' => (int)$this->request->getBodyParam('priority', 100),
            'enabled' => (bool)$this->request->getBodyParam('enabled', true),
        ]);

        if (!SiteRedirects::$plugin->redirects->saveRedirect($redirect)) {
            Craft::$app->getSession()->setError('Could not save redirect.');
            Craft::$app->getUrlManager()->setRouteParams([
                'redirect' => $redirect,
            ]);
            return null;
        }

        Craft::$app->getSession()->setNotice('Redirect saved.');

        return $this->redirectToPostedUrl();
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $id = (int)$this->request->getRequiredBodyParam('id');

        return $this->asJson([
            'success' => SiteRedirects::$plugin->redirects->deleteRedirectById($id),
        ]);
    }
}
