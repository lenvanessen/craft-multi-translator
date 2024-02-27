<?php

namespace digitalpulsebe\craftmultitranslator\jobs;

use \Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use digitalpulsebe\craftmultitranslator\helpers\EntryHelper;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use Exception;

class BulkTranslateJob extends BaseJob
{
    public array $entryIds;
    public string $sourceSiteHandle;
    public string $targetSiteHandle;

    public ?string $description = 'Translating entries...';

    public function execute($queue): void
    {
        $this->setProgress($queue, 1);

        $this->description = "Translating entries...";
        $errors = [];

        $sourceSite = Craft::$app->getSites()->getSiteByHandle($this->sourceSiteHandle);
        $targetSite = Craft::$app->getSites()->getSiteByHandle($this->targetSiteHandle);

        $entries = EntryHelper::all($this->entryIds, $sourceSite->id);

        $entryCount = count($entries);

        foreach ($entries as $i => $entry) {
            $iHuman = $i+1;

            $this->setProgress($queue, $i/$entryCount, "Translating entry $iHuman/$entryCount");
            $translatedElement = MultiTranslator::getInstance()->translate->translateEntry($entry, $sourceSite, $targetSite);
            
            if (!empty($translatedElement->errors)) {
                $errors[$translatedElement->id] = $translatedElement->errors;
            }
        }

        if (count($errors)) {
            $count = count($errors);
            throw new Exception("Validation errors for $count elements. Check the logs.");
        }

        $this->setProgress($queue, 100, 'done');
    }
}
