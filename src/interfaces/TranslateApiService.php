<?php

namespace digitalpulsebe\craftmultitranslator\interfaces;

interface TranslateApiService
{
    public function translate(string $sourceLocale, string $targetLocale, string $text = null): ?string;
}
