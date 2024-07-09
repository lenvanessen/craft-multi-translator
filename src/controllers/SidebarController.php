<?php

namespace digitalpulsebe\craftmultitranslator\controllers;

use \Craft;
use digitalpulsebe\craftmultitranslator\helpers\ElementHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use yii\web\Response;
use craft\web\Controller;

class SidebarController extends Controller
{
    public $enableCsrfValidation = false;

    public function actionTranslate(): Response
    {
        $this->requirePermission('multiTranslateContent');

        $elementId = $this->request->get('elementId');
        $elementType = $this->request->get('elementType');
        $sourceSiteId = $this->request->get('sourceSiteId');
        $targetSiteId = $this->request->get('targetSiteId');

        $element = ElementHelper::one($elementType, $elementId, $sourceSiteId);

        $sourceSite = Craft::$app->sites->getSiteById($sourceSiteId);
        $targetSite = Craft::$app->sites->getSiteById($targetSiteId);

        try {
            $translatedElement = MultiTranslator::getInstance()->translate->translateElement($element, $sourceSite, $targetSite);

            if (!empty($translatedElement->errors)) {
                $this->setFailFlash('Validation errors '.json_encode($translatedElement->errors));
                return $this->redirect($translatedElement->cpEditUrl);
            }

            return $this->asSuccess('Element translated', ['elementId' => $elementId], $translatedElement->cpEditUrl);
        } catch (\Throwable $throwable) {
            $target = ElementHelper::one($elementType, $elementId, $targetSiteId);
            Craft::$app->session->setError($throwable->getMessage());
            return $this->redirect($target->cpEditUrl);
        }

    }

}
