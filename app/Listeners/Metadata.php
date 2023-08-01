<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Util;
use Espo\Listeners\AbstractListener;
use Pim\SelectManagers\ProductAttributeValue;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // find multilingual attributes
        $multilingualAttributes = [];
        foreach ($data['attributes'] as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $multilingualAttributes[] = $attribute;
            }
        }

        $data['clientDefs']['Attribute']['dynamicLogic']['fields']['isMultilang']['visible']['conditionGroup'] = [
            [
                "type"      => "in",
                "attribute" => "type",
                "value"     => $multilingualAttributes
            ]
        ];

        // set type Hierarchy to Product entity
        $data['scopes']['Product']['type'] = 'Hierarchy';

        $data = $this->enableExportDisabledParamForPav($data);

        $data = $this->prepareClassificationAttributeMetadata($data);

        if ($this->getConfig()->get('behaviorOnCatalogChange', 'cascade') == 'cascade') {
            $data['clientDefs']['Product']['confirm']['catalogId'] = 'Product.messages.productCatalogChangeConfirm';
            $data['clientDefs']['Catalog']['relationshipPanels']['products']['selectConfirm'] = 'Product.messages.productCatalogChangeConfirm';
            $data['clientDefs']['Catalog']['relationshipPanels']['products']['unlinkConfirm'] = 'Product.messages.productCatalogChangeConfirm';
        }

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') == 'cascade') {
            $data['clientDefs']['Category']['deleteConfirmation'] = 'Category.messages.categoryRemoveConfirm';
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') == 'cascade') {
            $data['clientDefs']['Catalog']['relationshipPanels']['categories']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
            $data['clientDefs']['Category']['relationshipPanels']['catalogs']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
        }

        $data = $this->addTabPanels($data);

        $data = $this->addVirtualProductFields($data);

        $this->addLanguageBoolFiltersForPav($data);
        $this->addScopeBoolFilters($data);

        $event->setArgument('data', $data);
    }

    protected function addLanguageBoolFiltersForPav(array &$metadata): void
    {
        $metadata['clientDefs']['ProductAttributeValue']['boolFilterList'][] = ProductAttributeValue::createLanguagePrismBoolFilterName('main');
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $metadata['clientDefs']['ProductAttributeValue']['boolFilterList'][] = ProductAttributeValue::createLanguagePrismBoolFilterName($language);
            }
        }
    }

    protected function addScopeBoolFilters(array &$metadata): void
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return;
        }

        $dataManager = $this->getContainer()->get('dataManager');

        $channels = $dataManager->getCacheData('channels');
        if (empty($channels)) {
            try {
                $channels = $this->getContainer()->get('pdo')->query("SELECT id, `name` FROM `channel` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $channels = [];
            }
            if (!empty($channels)) {
                $dataManager->setCacheData('channels', $channels);
            }
        }

        foreach (['ProductAttributeValue', 'ProductAsset'] as $entityType) {
            $metadata['clientDefs'][$entityType]['channels'] = $channels;
            $callback = '\\Pim\\SelectManagers\\' . $entityType . '::createScopePrismBoolFilterName';
            $metadata['clientDefs'][$entityType]['boolFilterList'][] = call_user_func($callback, 'global');
            foreach ($channels as $channel) {
                $metadata['clientDefs'][$entityType]['boolFilterList'][] = call_user_func($callback, $channel['id']);
            }
        }
    }

    protected function addVirtualProductFields(array $metadata): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $metadata;
        }

        $dataManager = $this->getContainer()->get('dataManager');

        $attributes = $dataManager->getCacheData('attribute_product_fields');
        if ($attributes === null) {
            try {
                $attributes = $this->getContainer()->get('pdo')->query("SELECT * FROM attribute WHERE deleted=0 AND virtual_product_field=1")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $attributes = [];
            }

            $dataManager->setCacheData('attribute_product_fields', $attributes);
        }

        $languages = [];
        if ($this->getConfig()->get('isMultilangActive')) {
            $languages = $this->getConfig()->get('inputLanguageList', []);
        }

        foreach ($attributes as $attribute) {
            $fieldName = "{$attribute['code']}Attribute";

            $additionalFieldDefs = [
                'type'                      => 'varchar',
                'notStorable'               => true,
                'readOnly'                  => true,
                'layoutListDisabled'        => true,
                'layoutListSmallDisabled'   => true,
                'layoutDetailDisabled'      => true,
                'layoutDetailSmallDisabled' => true,
                'massUpdateDisabled'        => true,
                'filterDisabled'            => true,
                'importDisabled'            => true,
                'exportDisabled'            => true,
                'emHidden'                  => true,
            ];

            $defs = array_merge($additionalFieldDefs, [
                'type'                    => $attribute['type'],
                'layoutListDisabled'      => false,
                'layoutListSmallDisabled' => false,
                'isMultilang'             => !empty($attribute['is_multilang']),
                'attributeId'             => $attribute['id'],
                'attributeCode'           => $attribute['code'],
                'attributeName'           => $attribute['name'],
            ]);

            foreach ($languages as $language) {
                $languageName = $attribute['name'];
                if (isset($attribute['name_' . strtolower($language)])) {
                    $languageName = $attribute['name_' . strtolower($language)];;
                }
                $defs[Util::toCamelCase('attribute_name_' . strtolower($language))] = $languageName;
            }

            if (!empty($attribute['extensible_enum_id'])) {
                $defs['extensibleEnumId'] = $attribute['extensible_enum_id'];
            }

            if (!empty($attribute['measure_id'])) {
                $defs['measureId'] = $attribute['measure_id'];
                $metadata['entityDefs']['Product']['fields']["{$fieldName}UnitId"] = $additionalFieldDefs;
            }

            switch ($attribute['type']) {
                case 'asset':
                    $defs['assetType'] = $attribute['asset_type'];
                    break;
            }

            $metadata['entityDefs']['Product']['fields'][$fieldName] = $defs;
            switch ($attribute['type']) {
                case 'currency':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Currency"] = $additionalFieldDefs;
                    break;
                case 'asset':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Id"] = array_merge($additionalFieldDefs, [
                        'attributeId'    => $attribute['id'],
                        'attributeCode'  => $attribute['code'],
                        'assetFieldName' => $fieldName,
                    ]);
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Name"] = $additionalFieldDefs;
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}PathsData"] = array_merge($additionalFieldDefs, ['type' => 'jsonObject']);
                    break;
                case 'extensibleEnum':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Name"] = $additionalFieldDefs;
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}OptionData"] = array_merge($additionalFieldDefs, ['type' => 'jsonArray']);
                    break;
                case 'extensibleMultiEnum':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Names"] = array_merge($additionalFieldDefs, ['type' => 'jsonArray']);
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}OptionsData"] = array_merge($additionalFieldDefs, ['type' => 'jsonArray']);
                    break;
            }

            if (!empty($attribute['is_multilang'])) {
                $languageDefs = $defs;
                $languageDefs['isMultilang'] = false;
                $languageDefs['multilangField'] = $fieldName;

                foreach ($languages as $language) {
                    $languageFieldName = Util::toCamelCase($attribute['code'] . '_' . strtolower($language)) . 'Attribute';
                    $languageDefs['multilangLocale'] = $language;
                    switch ($defs['type']) {
                        case 'asset':
                            $metadata['entityDefs']['Product']['fields']["{$languageFieldName}Id"] = array_merge($defs, [
                                'type'            => 'varchar',
                                'attributeId'     => $attribute['id'],
                                'attributeCode'   => $attribute['code'],
                                'assetFieldName'  => $languageFieldName,
                                'multilangLocale' => $language,
                            ]);
                            $metadata['entityDefs']['Product']['fields']["{$languageFieldName}Name"] = $additionalFieldDefs;
                            $metadata['entityDefs']['Product']['fields']["{$languageFieldName}PathsData"] = array_merge($additionalFieldDefs, ['type' => 'jsonObject']);
                            break;
                    }

                    $metadata['entityDefs']['Product']['fields'][$languageFieldName] = $languageDefs;
                }
            }
        }

        return $metadata;
    }

    protected function addTabPanels(array $data): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $data;
        }

        $dataManager = $this->getContainer()->get('dataManager');

        $tabs = $dataManager->getCacheData('attribute_tabs');
        if ($tabs === null) {
            try {
                $tabs = $this->getContainer()->get('pdo')->query("SELECT id, `name` FROM attribute_tab WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $tabs = [];
            }
            $dataManager->setCacheData('attribute_tabs', $tabs);
        }

        foreach ($tabs as $tab) {
            $data['clientDefs']['Product']['bottomPanels']['detail'][] = [
                'name'                 => "tab_{$tab['id']}",
                'link'                 => 'productAttributeValues',
                'label'                => $tab['name'],
                'createAction'         => 'createRelatedConfigured',
                'selectAction'         => 'selectRelatedEntity',
                'selectBoolFilterList' => ['fromAttributesTab', 'onlyDefaultChannelAttributes'],
                'tabId'                => $tab['id'],
                'view'                 => 'pim:views/product/record/panels/product-attribute-values',
                "rowActionsView"       => "pim:views/product-attribute-value/record/row-actions/relationship-no-unlink-in-product",
                "recordListView"       => "pim:views/record/list-in-groups",
                "aclScopesList"        => [
                    "Attribute",
                    "AttributeGroup",
                    "ProductAttributeValue"
                ],
                "sortBy"               => "attribute.sortOrderInAttributeGroup",
                "asc"                  => true
            ];
        }

        return $data;
    }

    protected function prepareClassificationAttributeMetadata(array $data): array
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return $data;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return $data;
        }

        foreach ($locales as $locale) {
            $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
            $data['entityDefs']['ClassificationAttribute']['fields']["attributeName$camelCaseLocale"] = [
                "type"                      => "varchar",
                "notStorable"               => true,
                "default"                   => null,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"        => true,
                "filterDisabled"            => true,
                "importDisabled"            => true,
                "emHidden"                  => true
            ];
        }

        return $data;
    }

    /**
     * Enable exportDisabled parameter for ProductAttributeValue multi-lang fields
     *
     * @param array $data
     *
     * @return array
     */
    protected function enableExportDisabledParamForPav(array $data): array
    {
        // is multi-lang activated
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return $data;
        }

        // get locales
        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return $data;
        }

        foreach (['value', 'ownerUser', 'assignedUser'] as $field) {
            foreach ($locales as $locale) {
                $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
                if (isset($data['entityDefs']['ProductAttributeValue']['fields'][$field . $preparedLocale])) {
                    $data['entityDefs']['ProductAttributeValue']['fields'][$field . $preparedLocale]['exportDisabled'] = true;
                }
            }
        }

        return $data;
    }
}
