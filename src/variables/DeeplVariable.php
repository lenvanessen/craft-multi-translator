<?php
namespace digitalpulsebe\craftdeepltranslator\variables;

use DeepL\Translator;
use digitalpulsebe\craftdeepltranslator\DeeplTranslator;
use digitalpulsebe\craftdeepltranslator\models\Settings;

class DeeplVariable
{
    public function getUsage(): ?\DeepL\Usage
    {
        if (!empty($this->getSettings()->apiKey)) {
            try {
                return $this->getClient()->getUsage();
            } catch (\Throwable $throwable) {
                return null;
            }

        }

        return null;
    }
    public function getSupportedLanguages(): array
    {
        if (!empty($this->getSettings()->apiKey)) {
            try {
                return [
                    'source' => $this->getClient()->getSourceLanguages(),
                    'target' => $this->getClient()->getTargetLanguages(),
                ];
            } catch (\Throwable $throwable) {
                return [];
            }

        }

        return [];
    }

    public function getSettings(): Settings
    {
        return DeeplTranslator::getInstance()->getSettings();
    }

    public function getClient(): Translator
    {
        return DeeplTranslator::getInstance()->deepl->getClient();
    }
}
