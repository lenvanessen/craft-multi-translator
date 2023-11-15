<?php

namespace digitalpulsebe\craftdeepltranslator\services;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\Table;
use craft\models\Section;
use craft\models\Site;
use digitalpulsebe\craftdeepltranslator\DeeplTranslator;

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
     * @param bool $translate set false for copy only
     * @return Entry
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \yii\base\Exception
     */
    public function translateEntry(Entry $source, Site $sourceSite, Site $targetSite, bool $translate = true)
    {
        $translatedValues = $this->translateElement($source, $sourceSite, $targetSite, $translate);

        $targetEntry = $this->findTargetEntry($source, $targetSite->id);

        if (isset($translatedValues['title'])) {
            $targetEntry->title = $translatedValues['title'];

            if (DeeplTranslator::getInstance()->getSettings()->resetSlug) {
                $targetEntry->slug = null;
            }

            unset($translatedValues['title']);
        }

        $targetEntry->setFieldValues($translatedValues);

        \Craft::$app->elements->saveElement($targetEntry);
        return $targetEntry;
    }


    /**
     * @param Element $source
     * @param Site $sourceSite
     * @param Site $targetSite
     * @param bool $translate set false for copy only
     * @return array
     */
    public function translateElement(Element $source, Site $sourceSite, Site $targetSite, bool $translate = true): array
    {
        $target = [];

        if ($source->title) {
            if ($translate) {
                $target['title'] = DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $source->title);
            } else {
                $target['title'] = $source->title;
            }
        }

        foreach ($source->fieldLayout->getCustomFields() as $field) {
            $translatedValue = null;
            $fieldTranslatable = $field->translationMethod != Field::TRANSLATION_METHOD_NONE;
            $processField = ($fieldTranslatable || !$translate); // if translatable, or just copy

            if (in_array(get_class($field), static::$textFields) && $processField) {
                // normal text fields
                $translatedValue = $this->translateTextField($source, $field, $sourceSite, $targetSite, $translate);
            } elseif (in_array(get_class($field), static::$matrixFields)) {
                // dig deeper in Matrix fields
                $translatedValue = $this->translateMatrixField($source, $field, $sourceSite, $targetSite, $translate);
            } elseif (get_class($field) == Table::class && $processField) {
                // loop over table
                $translatedValue = $this->translateTable($source, $field, $sourceSite, $targetSite, $translate);
            } elseif (get_class($field) == 'lenz\linkfield\fields\LinkField' && $processField) {
                // translate linkfield custom label
                $translatedValue = $this->translateLinkField($source, $field, $sourceSite, $targetSite, $translate);
            }

            if ($translatedValue) {
                $target[$field->handle] = $translatedValue;
            }
        }

        return $target;
    }

    public function translateTextField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite, bool $translate = true): ?string
    {
        $value = $field->serializeValue($element->getFieldValue($field->handle), $element);

        if (!$translate) {
            return $value;
        }

        return DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $value);
    }

    public function translateTable(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite, bool $translate = true): array
    {
        $sourceData = $field->serializeValue($element->getFieldValue($field->handle), $element);

        if (!$translate) {
            return $sourceData;
        }

        $targetData = [];

        if (is_array($sourceData)) {
            foreach ($sourceData as $sourceRow) {
                $targetRow = [];
                foreach ($sourceRow as $columnName => $value) {
                    $targetRow[$columnName] = DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $value);
                }
                $targetData[] = $targetRow;
            }
        }

        return $targetData;
    }

    public function translateMatrixField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite, bool $translate = true): array
    {
        $query = $element->getFieldValue($field->handle);

        // serialize current value
        $serialized = $element->getSerializedFieldValues([$field->handle])[$field->handle];

        if (!$translate) {
            return $serialized;
        }

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

    public function translateLinkField(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite, bool $translate = true): ?array
    {
        $value = $element->getFieldValue($field->handle);
        if ($value) {
            try {
                $array = $value->toArray();

                if (!$translate) {
                    return $array;
                }

                if (!empty($array['customText'])) {
                    $array['customText'] = DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $array['customText']);
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
        $targetEntry = Entry::find()->status(null)->id($source->id)->siteId($targetSiteId)->one();

        if (empty($targetEntry)) {
            // we need to create one for this target site
            if ($source->section->propagationMethod == Section::PROPAGATION_METHOD_CUSTOM) {
                // enable for site first
                $sitesEnabled = $source->getEnabledForSite();
                if (is_array($sitesEnabled)) {
                    $sitesEnabled[$targetSiteId] = true;
                } else {
                    $sitesEnabled = [
                        $source->site->id => true,
                        $targetSiteId => true,
                    ];
                }

                $source->setEnabledForSite($sitesEnabled);
                Craft::$app->elements->saveElement($source);
                $targetEntry = Entry::find()->status(null)->id($source->id)->siteId($targetSiteId)->one();
            } elseif ($source->section->propagationMethod == Section::PROPAGATION_METHOD_ALL) {
                // it should have been there, so propagate
                $targetEntry = Craft::$app->elements->propagateElement($source, $targetSiteId, false);
            } else {
                // duplicate to the target site
                $targetEntry = Craft::$app->elements->duplicateElement($source, ['siteId' => $targetSiteId]);
            }
        }

        return $targetEntry;
    }
}
