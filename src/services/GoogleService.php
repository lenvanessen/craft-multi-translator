<?php

namespace digitalpulsebe\craftmultitranslator\services;

use craft\helpers\App;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use Google\Cloud\Translate\V2\TranslateClient;

class GoogleService extends ApiService
{

    protected ?TranslateClient $_client = null;

    public function getClient()
    {
        if (!$this->_client) {
            $apiKey = App::parseEnv(MultiTranslator::getInstance()->getSettings()->googleApiKey);
            $this->_client = new TranslateClient([
                'key' => $apiKey
            ]);
        }

        return $this->_client;
    }

    public function translate(string $sourceLocale, string $targetLocale, string $text = null): ?string
    {
        if ($text) {
            $options = [
                'target' => $this->targetLocale($targetLocale),
            ];

            if (!$this->getSettings()->detectSourceLanguage) {
                $options['source'] = $sourceLocale;
            }

            $response = $this->getClient()->translate($text, $options);

            return $response['text'];
        }

        return null;
    }
}
