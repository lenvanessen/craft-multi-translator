<?php

namespace digitalpulsebe\craftdeepltranslator\services;

use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fields\Table;
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

    public function translateEntry(Entry $source, Site $sourceSite, Site $targetSite)
    {
        $translatedValues = $this->translateElement($source, $sourceSite, $targetSite);

        $targetEntry = Entry::find()->id($source->id)->siteId($targetSite->id)->one();

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

    public function translateElement(Element $source, Site $sourceSite, Site $targetSite): array
    {
        $target = [];

        if ($source->title) {
            $target['title'] = DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $source->title);
        }

        foreach ($source->fieldLayout->getCustomFields() as $field) {
            $translatedValue = null;

            if (
                in_array(get_class($field), static::$textFields)
                && $field->translationMethod != Field::TRANSLATION_METHOD_NONE
            ) {
                // normal text fields
                $translatedValue = $this->translateTextField($source, $field, $sourceSite, $targetSite);
            } elseif (in_array(get_class($field), static::$matrixFields)) {
                // dig deeper in Matrix fields
                $translatedValue = $this->translateMatrixField($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == Table::class) {
                // loop over table
                $translatedValue = $this->translateTable($source, $field, $sourceSite, $targetSite);
            } elseif (get_class($field) == 'lenz\linkfield\fields\LinkField') {
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

        return DeeplTranslator::getInstance()->deepl->translate($sourceSite->language, $targetSite->language, $value);
    }

    public function translateTable(Element $element, FieldInterface $field, Site $sourceSite, Site $targetSite): array
    {
        $sourceData = $field->serializeValue($element->getFieldValue($field->handle), $element);
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
}
