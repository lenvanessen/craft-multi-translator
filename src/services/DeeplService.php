<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use DeepL\Translator;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class DeeplService extends ApiService
{

    protected ?Translator $_client = null;

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv(MultiTranslator::getInstance()->getSettings()->deeplApiKey);
            $this->_client = new Translator($apiKey);;
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        $defaultOptions = [
            'tag_handling' => 'html',
            'formality' => $this->getSettings()->deeplFormality,
            'preserve_formatting' => $this->getSettings()->deeplPreserveFormatting,
        ];

        if ($this->getSettings()->detectSourceLanguage) {
            $sourceLocale = null;
        }

        if ($text) {
            return $this->getClient()->translateText($text, $this->sourceLocale($sourceLocale), $this->targetLocale($targetLocale), $defaultOptions);
        }

        return null;
    }

    public function sourceLocale($raw): ?string
    {
        if (!empty($raw)) {
            $locale = substr($raw, 0, 2);
        }

        return $locale;
    }
    public function targetLocale($raw): string
    {
        if (in_array($raw, ['en-GB', 'en-US', 'pt-PT', 'pt-BR'])) {
            return $raw;
        }

        $locale = substr($raw, 0, 2);

        if ($locale == 'en') {
            return MultiTranslator::getInstance()->getSettings()->defaultEnglish;
        }

        if ($locale == 'pt') {
            return 'pt-PT';
        }

        return $locale;
    }

    protected function getSettings(): \digitalpulsebe\craftmultitranslator\models\Settings
    {
        return MultiTranslator::getInstance()->getSettings();
    }
}
