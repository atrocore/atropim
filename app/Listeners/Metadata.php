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

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class Metadata
 */
class Metadata extends AbstractListener
{
    protected const ATTRIBUTE_TABS_FILE = 'data/cache/attribute_tabs.json';

    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

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

        $data['entityDefs']['Asset']['fields']['preview']['view'] = 'pim:views/asset/fields/preview';

        // set data
        $event->setArgument('data', $data);
    }

    protected function addTabPanels(array $data): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $data;
        }

        if (!file_exists(self::ATTRIBUTE_TABS_FILE) || empty(file_get_contents(self::ATTRIBUTE_TABS_FILE))) {
            try {
                $tabs = $this->getContainer()->get('pdo')->query("SELECT id, name FROM attribute_tab WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {
                $tabs = [];
            }
            if (!empty($tabs)) {
                Util::createDir('data/cache');
                file_put_contents(self::ATTRIBUTE_TABS_FILE, Json::encode($tabs));
            }
        } else {
            $tabs = Json::decode(file_get_contents(self::ATTRIBUTE_TABS_FILE), true);
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
                "layoutMassUpdateDisabled"  => true,
                "filterDisabled"     => true,
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
