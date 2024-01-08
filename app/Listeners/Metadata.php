<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Utils\Util;
use Atro\Listeners\AbstractListener;
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
            $connection = $this->getEntityManager()->getConnection();
            try {
                $channels = $connection->createQueryBuilder()
                    ->select('c.id,c.name')
                    ->from($connection->quoteIdentifier('channel'), 'c')
                    ->where('c.deleted = :false')
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->fetchAllAssociative();
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
            $connection = $this->getEntityManager()->getConnection();
            try {
                $attributes = $connection->createQueryBuilder()
                    ->select('t.*')
                    ->from($connection->quoteIdentifier('attribute'), 't')
                    ->where('t.deleted = :false')
                    ->andWhere('t.virtual_product_field = :true')
                    ->setParameter('true', true, Mapper::getParameterType(true))
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->fetchAllAssociative();
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
                $defs['unitIdField'] = true;
                $metadata['entityDefs']['Product']['fields']["{$fieldName}UnitId"] = $additionalFieldDefs;
            }

            switch ($attribute['type']) {
                case 'asset':
                    $defs['assetType'] = $attribute['asset_type'];
                    break;
            }

            $metadata['entityDefs']['Product']['fields'][$fieldName] = $defs;
            switch ($attribute['type']) {
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
            $connection = $this->getEntityManager()->getConnection();
            try {
                $tabs = $connection->createQueryBuilder()
                    ->select('t.id, t.name')
                    ->from($connection->quoteIdentifier('attribute_tab'), 't')
                    ->where('t.deleted = :false')
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->fetchAllAssociative();
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

        foreach (['value'] as $field) {
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
