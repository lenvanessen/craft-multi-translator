<?php

namespace digitalpulsebe\craftmultitranslator\jobs;

use \Craft;
use craft\elements\Entry;
use craft\queue\BaseJob;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class BulkTranslateJob extends BaseJob
{
    const MODE_TRANSLATE = 'translate';
    const MODE_COPY = 'copy';

    public array $entryIds;
    public string $sourceSiteHandle;
    public string $targetSiteHandle;
    public string $mode;

    public ?string $description = 'Translating entries...';

    public function execute($queue): void
    {
        $this->setProgress($queue, 1);

        $copyMode = $this->mode == static::MODE_COPY;
        $modeDescription = $copyMode ? 'Copying' : 'Translating';
        $this->description = "$modeDescription entries...";

        $sourceSite = Craft::$app->getSites()->getSiteByHandle($this->sourceSiteHandle);
        $targetSite = Craft::$app->getSites()->getSiteByHandle($this->targetSiteHandle);

        $entries = Entry::find()->status(null)->id($this->entryIds)->siteId($sourceSite->id)->all();

        $entryCount = count($entries);

        foreach ($entries as $i => $entry) {
            $iHuman = $i+1;

            $this->setProgress($queue, $i/$entryCount, "$modeDescription entry $iHuman/$entryCount");
            MultiTranslator::getInstance()->translate->translateEntry($entry, $sourceSite, $targetSite, !$copyMode);
        }

        $this->setProgress($queue, 100, 'done');
    }
}
