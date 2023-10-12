<?php

namespace digitalpulsebe\craftdeepltranslator\services;

use craft\base\Component;
use craft\helpers\App;
use DeepL\Translator;
use digitalpulsebe\craftdeepltranslator\DeeplTranslator;

class DeeplService extends Component
{

    protected ?Translator $_client = null;

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv(DeeplTranslator::getInstance()->getSettings()->apiKey);
            $this->_client = new Translator($apiKey);;
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale, string $targetLocale, string $text = null): ?string
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
        if (in_array($raw, ['en-GB', 'en-US'])) {
            return $raw;
        }

        $locale = substr($raw, 0, 2);

        if ($locale == 'en') {
            return DeeplTranslator::getInstance()->getSettings()->defaultEnglish;
        }

        return $locale;
    }

    protected function getSettings(): \digitalpulsebe\craftdeepltranslator\models\Settings
    {
        return DeeplTranslator::getInstance()->getSettings();
    }
}
