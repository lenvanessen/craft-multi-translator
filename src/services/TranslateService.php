<?php

namespace digitalpulsebe\craftmultitranslator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\Table;
use craft\models\Section;
use craft\models\Site;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

class TranslateService extends Component
{
    static array $textFields = [
        'craft\fields\PlainText',
        'craft\redactor\Field',
        'craft\ckeditor\Field',
    ];
    static array $matrixFields = [
        'craft\fields\Matrix',
        'benf\neo\Field',
        'verbb\supertable\fields\SuperTableField',
    ];

    /**
     * @param Entry $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @return Entry
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function translateEntry(Entry $source, Site $sourceSite, Site $targetSite)
    {
        $translatedValues = $this->translateElement($source, $sourceSite, $targetSite);

        $targetEntry = $this->findTargetEntry($source, $targetSite->id);

        if (isset($translatedValues['title'])) {
            $targetEntry->title = $translatedValues['title'];

            if (MultiTranslator::getInstance()->getSettings()->resetSlug) {
                $targetEntry->slug = null;
            }

            unset($translatedValues['title']);
        }

        $targetEntry->setFieldValues($translatedValues);

        if ($targetEntry->getIsDraft()) {
            \Craft::$app->drafts->saveElementAsDraft($targetEntry);
        } else {
            \Craft::$app->elements->saveElement($targetEntry);
        }

        return $targetEntry;
    }


    /**
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param bool $translate set false for copy only
     * @return array
     */
    public function translateElement(Element $source, Site $sourceSite, Site $targetSite): array
    {
        $target = [];

        if ($source->title) {
            $target['title'] = $this->translateText($sourceSite->language, $targetSite->language, $source->title);
        }

        foreach ($source->fieldLayout->getCustomFields() as $field) {
            $translatedValue = null;
            $fieldTranslatable = $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
            $processField = boolval($fieldTranslatable); // if translatable

            if (in_array(get_class($field), static::$textFields) && $processField) {
                // normal text fields
                $translatedValue = $this->translateTextField($source, $field, $sourceSite, $targetSite);
            } elseif (in_array(get_class($field), static::$matrixFields)) {
                // dig deeper in Matrix fields
                $translatedValue = $this->translateMatrixField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == Table::class && $processField) {
                // loop over table
                $translatedValue = $this->translateTable($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'lenz\linkfield\fields\LinkField' && $processField) {
                // translate linkfield custom label
                $translatedValue = $this->translateLinkField($source, $field, $sourceSite, $targetSite);
            }

            if ($translatedValue) {
                $target[$field->handle] = $translatedValue;
            }
        }

        return $target;
    }

    public function translateTextField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?string
    {
        $value = $field->serializeValue($element->getFieldValue($field->handle), $element);

        return $this->translateText($sourceSite->language, $targetSite->language, $value);
    }

    public function translateTable(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): array
    {
        $sourceData = $field->serializeValue($element->getFieldValue($field->handle), $element);

        $targetData = [];

        if (is_array($sourceData)) {
            $textColumns = [];
            foreach ($field->columns as $columnName => $columnConfig) {
                if (in_array($columnConfig['type'], ['singleline', 'multiline', 'heading'])) {
                    // only process types with text
                    $textColumns[] = $columnName;
                }
            }

            foreach ($sourceData as $sourceRow) {
                $targetRow = [];
                foreach ($sourceRow as $columnName => $value) {
                    if (in_array($columnName, $textColumns)) {
                        $targetRow[$columnName] = $this->translateText($sourceSite->language, $targetSite->language, $value);
                    } else {
                        $targetRow[$columnName] = $value;
                    }
                }
                $targetData[] = $targetRow;
            }
        }

        return $targetData;
    }

    public function translateMatrixField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): array
    {
        $query = $element->getFieldValue($field->handle);

        // serialize current value
        $serialized = $element->getSerializedFieldValues([$field->handle])[$field->handle];

        foreach ($query->all() as $matrixElement) {
            $translatedMatrixValues = $this->translateElement($matrixElement, $sourceSite, $targetSite);
            foreach ($translatedMatrixValues as $matrixFieldHandle => $value) {
                // only set translated values in matrix array
                if ($value && isset($serialized[$matrixElement->id])) {
                    $serialized[$matrixElement->id]['fields'][$matrixFieldHandle] = $value;
                }
            }
        }

        return $serialized;
    }

    public function translateLinkField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): ?array
    {
        $value = $element->getFieldValue($field->handle);
        if ($value) {
            try {
                $array = $value->toArray();

                if (!empty($array['customText'])) {
                    $array['customText'] = $this->translateText($sourceSite->language, $targetSite->language, $array['customText']);
                }
                return $array;
            } catch (\Throwable $throwable) {
                // too bad, f*** linkfields
                return null;
            }
        }
        return null;
    }

    public function findTargetEntry(Entry $source, int $targetSiteId): Entry
    {
        $targetEntry = Entry::find()->status(null)->drafts(null)->id($source->id)->siteId($targetSiteId)->one();

        if (empty($targetEntry)) {
            // we need to create one for this target site
            if ($source->section->propagationMethod == Section::PROPAGATION_METHOD_CUSTOM) {
                // create for site first, but keep enabled status
                $sitesEnabled = $source->getEnabledForSite();
                if (is_array($sitesEnabled) && !isset($sitesEnabled[$targetSiteId])) {
                    $sitesEnabled[$targetSiteId] = $source->enabledForSite;
                } else {
                    $sitesEnabled = [
                        $source->site->id => $source->enabledForSite,
                        $targetSiteId => $source->enabledForSite,
                    ];
                }

                $source->setEnabledForSite($sitesEnabled);

                if ($source->getIsDraft()) {
                    Craft::$app->drafts->saveElementAsDraft($source);
                } else {
                    Craft::$app->elements->saveElement($source);
                }

                $targetEntry = Entry::find()->status(null)->drafts(null)->id($source->id)->siteId($targetSiteId)->one();
            } elseif ($source->section->propagationMethod == Section::PROPAGATION_METHOD_ALL) {
                // it should have been there, so propagate
                $targetEntry = Craft::$app->elements->propagateElement($source, $targetSiteId, false);
            } else {
                // duplicate to the target site
                if ($source->getIsDraft()) {
                    $targetEntry = clone $source;
                    $targetEntry->siteId = $targetSiteId;
                    Craft::$app->drafts->saveElementAsDraft($targetEntry);
                } else {
                    $targetEntry = Craft::$app->elements->duplicateElement($source, ['siteId' => $targetSiteId]);
                }

            }
        }

        return $targetEntry;
    }

    public function translateText(string $sourceLocale = null, string $targetLocale = null, string $text = null): ?string
    {
        $provider = MultiTranslator::getInstance()->getSettings()->translationProvider;
        if ($provider == 'google') {
            return MultiTranslator::getInstance()->google->translate($sourceLocale, $targetLocale, $text);
        } else {
            return MultiTranslator::getInstance()->deepl->translate($sourceLocale, $targetLocale, $text);
        }
    }
}
