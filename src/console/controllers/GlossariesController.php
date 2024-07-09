<?php

namespace digitalpulsebe\craftmultitranslator\console\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use craft\console\Controller;
use yii\console\ExitCode;

class GlossariesController extends Controller
{

    /**
     * list existing DeepL glossaries in API
     * @return int
     */
    public function actionList(): int
    {
        dump(MultiTranslator::getInstance()->deepl->listGlossaries());

        return ExitCode::OK;
    }

    /**
     * delete one DeepL glossary by ID
     * @return int
     */
    public function actionDelete($id): int
    {
        MultiTranslator::getInstance()->deepl->deleteGlossary($id);

        return ExitCode::OK;
    }

}
