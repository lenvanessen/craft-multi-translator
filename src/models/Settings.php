<?php

namespace digitalpulsebe\craftmultitranslator\models;

use Craft;
use craft\base\Model;

/**
 * Multi Translator settings
 */
class Settings extends Model
{
    /**
     * @var string provider 'google' or 'deepl'
     */
    public string $translationProvider = 'deepl';

    /**
     * API credentials to connect to the API
     * @var string
     */
    public string $deeplApiKey = '';

    /**
     * clear the slug when setting a translated title
     * @var bool
     */
    public bool $resetSlug = true;

    /**
     * default English region for non-regional English
     * @var string
     */
    public string $defaultEnglish = 'en-US';

    /**
     * when enabled, we don't send the source language to the api
     * @var bool
     */
    public bool $detectSourceLanguage = false;

    /**
     * when enabled log info about translations
     * @var bool
     */
    public bool $debug = false;

    /**
     * time to reserve for the queue job when translating in bulk
     * @var int
     */
    public int $queueJobTtr = 3600;

    /**
     * controls automatic-formatting-correction. Set to true to prevent automatic-correction of formatting, default: false.
     * https://github.com/DeepLcom/deepl-php#text-translation-options
     * @var bool
     */
    public bool $deeplPreserveFormatting = false;

    /**
     * controls whether translations should lean toward informal or formal language. This option is only available for some target languages
     * https://github.com/DeepLcom/deepl-php#text-translation-options
     * @var bool
     */
    public string $deeplFormality = 'default';

    /**
     * API credentials to connect to the Google Cloud API
     * @var string
     */
    public string $googleApiKey = '';

    /**
     * API credentials to connect to the OpenAI API (ChatGPT)
     * @var string
     */
    public string $openAiKey = '';

    /**
     * Model for the OpenAI API
     * read more: https://platform.openai.com/docs/models/model-endpoint-compatibility
     * @var string
     */
    public string $openAiModel = 'gpt-3.5-turbo';

    /**
     * Temperature setting for the OpenAI API
     * read more: https://platform.openai.com/docs/api-reference/chat/create#chat-create-temperature
     * @var string
     */
    public float $openAiTemperature = 0.5;


}
