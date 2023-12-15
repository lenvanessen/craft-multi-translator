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
     * API credentials to connect to the API
     * @var string
     */
    public string $apiKey = '';

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


}
