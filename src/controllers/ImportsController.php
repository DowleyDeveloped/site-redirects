<?php

namespace dowleydeveloped\siteredirects\controllers;

use Craft;
use craft\web\Controller;
use craft\web\UploadedFile;
use dowleydeveloped\siteredirects\SiteRedirects;
use yii\web\Response;

class ImportsController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionIndex(): Response
    {
        return $this->renderTemplate('site-redirects/imports/index');
    }

    public function actionUpload(): ?Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('csvFile');
        $dryRun = (bool)$this->request->getBodyParam('dryRun', false);

        if (!$file) {
            Craft::$app->getSession()->setError('Please upload a CSV file.');
            return null;
        }

        $tempPath = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $file->name;
        $file->saveAs($tempPath);

        $results = SiteRedirects::$plugin->imports->importCsv($tempPath, $dryRun);

        if (!empty($results['errors'])) {
            Craft::$app->getSession()->setError('Import completed with errors.');
        } else {
            Craft::$app->getSession()->setNotice('Import completed successfully.');
        }

        Craft::$app->getUrlManager()->setRouteParams([
            'results' => $results,
        ]);

        return null;
    }
}
