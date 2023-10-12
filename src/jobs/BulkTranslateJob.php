<?php

namespace digitalpulsebe\craftdeepltranslator\jobs;

use \Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use digitalpulsebe\craftdeepltranslator\DeeplTranslator;

class BulkTranslateJob extends BaseJob
{

    public array $entryIds;
    public string $sourceSiteHandle;
    public string $targetSiteHandle;

    public ?string $description = 'Translating entries...';

    public function execute($queue): void
    {
        $entries = Entry::find()->id($this->entryIds)->all();

        $this->setProgress($queue, 1);

        $sourceSite = Craft::$app->getSites()->getSiteByHandle($this->sourceSiteHandle);
        $targetSite = Craft::$app->getSites()->getSiteByHandle($this->targetSiteHandle);

        $entryCount = count($entries);

        foreach ($entries as $i => $entry) {
            $iHuman = $i+1;
            $this->setProgress($queue, $i/$entryCount, "Translating entry $iHuman/$entryCount");
            DeeplTranslator::getInstance()->translate->translateEntry($entry, $sourceSite, $targetSite);
        }

        $this->setProgress($queue, 100, 'done');
    }
}
