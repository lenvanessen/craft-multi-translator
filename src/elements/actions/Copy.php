<?php

namespace digitalpulsebe\craftmultitranslator\elements\actions;

use Craft;
use craft\base\ElementAction;
use digitalpulsebe\craftmultitranslator\MultiTranslator;
use digitalpulsebe\craftmultitranslator\jobs\BulkTranslateJob;
use yii\web\UnauthorizedHttpException;

/**
 * Translate element action
 */
class Copy extends ElementAction
{
    public string $sourceSiteHandle;
    public string $targetSiteHandle = '';

    public static function displayName(): string
    {
        return 'Copy';
    }

    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
    new Craft.ElementActionTrigger({
        type: $type,
        bulk: true,
        // Return whether the action should be available depending on which elements are selected
        validateSelection: (selectedItems) {
          return true;
        },
    });
})();
JS, [static::class]);

        return Craft::$app->getView()->renderTemplate('multi-translator/_actions/copy.twig');
    }

    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $entryIds = $query->ids();

        if (!\Craft::$app->user->checkPermission('multiCopyContent')) {
            throw new UnauthorizedHttpException('You are not allowed to copy Entries');
        }

        Craft::$app
            ->getQueue()
            ->ttr(MultiTranslator::getInstance()->getSettings()->queueJobTtr)
            ->push(new BulkTranslateJob([
                'entryIds' => $entryIds,
                'sourceSiteHandle' => $this->sourceSiteHandle,
                'targetSiteHandle' => $this->targetSiteHandle,
                'description' => 'Copying '.count($entryIds).' entries...',
                'mode' => BulkTranslateJob::MODE_COPY
            ]))
        ;

        $this->setMessage('Added to queue');

        return true;
    }
}
