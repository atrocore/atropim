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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Listeners\AbstractListener;

class Metadata extends AbstractListener
{
    public function modify(Event $event): void
    {
        $data = $event->getArgument('data');

        // set type Hierarchy to Product entity
        $data['scopes']['Product']['type'] = 'Hierarchy';

        $data = $this->enableExportDisabledParamForPav($data);

        $data = $this->prepareProductFamilyAttributeMetadata($data);

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

        $event->setArgument('data', $data);
    }

    protected function addVirtualProductFields(array $metadata): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $metadata;
        }

        $dataManager = $this->getContainer()->get('dataManager');

        $attributes = $dataManager->getCacheData('attribute_product_fields');
        if (empty($attributes)) {
            try {
                $attributes = $this->getContainer()->get('pdo')->query("SELECT * FROM attribute WHERE deleted=0 AND virtual_product_field=1")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $attributes = [];
            }

            if (!empty($attributes)) {
                $dataManager->setCacheData('attribute_product_fields', $attributes);
            }
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

            switch ($attribute['type']) {
                case 'unit':
                    if (!empty($attribute['data'])) {
                        $data = @json_decode($attribute['data'], true);
                    }
                    if (!empty($data['field']['measure'])) {
                        $defs['measure'] = $data['field']['measure'];
                    }

                    if (empty($defs['measure']) && !empty($attribute['type_value'])) {
                        $typeValue = @json_decode($attribute['type_value'], true);
                        if (!empty($typeValue[0])) {
                            $defs['measure'] = $typeValue[0];
                        }
                    }
                    break;
                case 'asset':
                    $defs['assetType'] = $attribute['asset_type'];
                    break;
                case 'enum':
                case 'multiEnum':
                    $defs['options'] = [];
                    if (!empty($attribute['type_value'])) {
                        $typeValue = @json_decode($attribute['type_value'], true);
                        if (!empty($typeValue)) {
                            $defs['options'] = $typeValue;
                        }
                        foreach ($languages as $language) {
                            $defs[Util::toCamelCase('options_' . strtolower($language))] = $defs['options'];
                            if (!empty($attribute['type_value_' . strtolower($language)])) {
                                $languageTypeValue = @json_decode($attribute['type_value_' . strtolower($language)], true);
                                if (!empty($typeValue)) {
                                    $defs[Util::toCamelCase('options_' . strtolower($language))] = $languageTypeValue;
                                }
                            }
                        }
                    }
                    break;
            }

            $metadata['entityDefs']['Product']['fields'][$fieldName] = $defs;
            switch ($attribute['type']) {
                case 'currency':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Currency"] = $additionalFieldDefs;
                    break;
                case 'unit':
                    $metadata['entityDefs']['Product']['fields']["{$fieldName}Unit"] = $additionalFieldDefs;
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
            }

            if (!empty($attribute['is_multilang'])) {
                $languageDefs = $defs;
                $languageDefs['isMultilang'] = false;
                $languageDefs['multilangField'] = $fieldName;

                foreach ($languages as $language) {
                    $languageFieldName = Util::toCamelCase($attribute['code'] . '_' . strtolower($language)) . 'Attribute';
                    $languageDefs['multilangLocale'] = $language;
                    switch ($defs['type']) {
                        case 'enum':
                        case 'multiEnum':
                            $languageDefs['optionsOriginal'] = $defs['options'];
                            $languageDefs['options'] = $languageDefs[Util::toCamelCase('options_' . strtolower($language))];
                            break;
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
        if (empty($tabs)) {
            try {
                $tabs = $this->getContainer()->get('pdo')->query("SELECT id, `name` FROM attribute_tab WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $tabs = [];
            }
            if (!empty($tabs)) {
                $dataManager->setCacheData('attribute_tabs', $tabs);
            }
        }

        foreach ($tabs as $tab) {
            $data['clientDefs']['Product']['bottomPanels']['detail'][] = [
                'name'                 => "tab_{$tab['id']}",
                'link'                 => 'productAttributeValues',
                'label'                => $tab['name'],
                'createAction'         => 'createRelatedConfigured',
                'selectAction'         => 'selectRelatedEntity',
                'selectBoolFilterList' => ['notLinkedProductAttributeValues', 'fromAttributesTab'],
                'tabId'                => $tab['id'],
                'view'                 => 'pim:views/product/record/panels/product-attribute-values',
                "rowActionsView"       => "pim:views/product-attribute-value/record/row-actions/relationship-no-unlink-in-product",
                "recordListView"       => "pim:views/product-attribute-value/record/list-in-product",
                "aclScopesList"        => [
                    "Attribute",
                    "AttributeGroup",
                    "ProductAttributeValue"
                ],
                "sortBy"               => "attribute.sortOrder",
                "asc"                  => true
            ];
        }

        return $data;
    }

    protected function prepareProductFamilyAttributeMetadata(array $data): array
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
            $data['entityDefs']['ProductFamilyAttribute']['fields']["attributeName$camelCaseLocale"] = [
                "type"                      => "varchar",
                "notStorable"               => true,
                "default"                   => null,
                "layoutListDisabled"        => true,
                "layoutListSmallDisabled"   => true,
                "layoutDetailDisabled"      => true,
                "layoutDetailSmallDisabled" => true,
                "massUpdateDisabled"  => true,
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
