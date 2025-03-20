<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
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

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') == 'cascade') {
            $data['clientDefs']['Category']['deleteConfirmation'] = 'Category.messages.categoryRemoveConfirm';
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') == 'cascade') {
            $data['clientDefs']['Catalog']['relationshipPanels']['categories']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
            $data['clientDefs']['Category']['relationshipPanels']['catalogs']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
        }


        $data = $this->addTabPanels($data);

        $this->addLanguageBoolFiltersForPav($data);
        $this->addOnlyExtensibleEnumOptionForCABoolFilter($data);

        $this->defineClassificationViewForProduct($data);

        $event->setArgument('data', $data);
    }

    protected function addLanguageBoolFiltersForPav(array &$metadata): void
    {
        if ($this->getConfig()->get('isMultilangActive') && !empty($this->getConfig()->get('inputLanguageList', []))) {
            $metadata['clientDefs']['ProductAttributeValue']['boolFilterList'][] = 'includeUniLingualValues';
        }
    }

    protected function addTabPanels(array $data): array
    {
        if (!$this->getConfig()->get('isInstalled', false)) {
            return $data;
        }

        $tabs = $this->getEntityManager()->getRepository('AttributeTab')->getSimplifyTabs();
        foreach ($tabs as $tab) {
            $data['clientDefs']['Product']['bottomPanels']['detail'][] = [
                'name'                       => "tab_{$tab['id']}",
                'link'                       => 'productAttributeValues',
                'label'                      => $tab['name'],
                'createAction'               => 'createRelatedConfigured',
                'selectAction'               => 'selectRelatedEntity',
                'selectBoolFilterList'       => ['fromAttributesTab'],
                'tabId'                      => $tab['id'],
                'view'                       => 'pim:views/product/record/panels/product-attribute-values',
                "rowActionsView"             => "pim:views/product-attribute-value/record/row-actions/relationship-no-unlink-in-product",
                "recordListView"             => "pim:views/record/list-in-groups",
                "compareRecordsView"         => "pim:views/product/record/compare/product-attribute-values",
                "compareInstanceRecordsView" => "pim:views/product/record/compare/product-attribute-values-instance",
                "aclScopesList"              => [
                    "Attribute",
                    "AttributeGroup",
                    "ProductAttributeValue"
                ],
                "sortBy"                     => "attribute.sortOrder",
                "asc"                        => true
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

    protected function addOnlyExtensibleEnumOptionForCABoolFilter(&$metadata)
    {
        $metadata['clientDefs']['ExtensibleEnumOption']['boolFilterList'][] = "onlyForClassificationAttributesUsingPavId";
        $metadata['clientDefs']['ExtensibleEnumOption']['hiddenBoolFilterList'][] = "onlyForClassificationAttributesUsingPavId";

        $metadata['clientDefs']['ExtensibleEnumOption']['boolFilterList'][] = "onlyExtensibleEnumOptionIds";
        $metadata['clientDefs']['ExtensibleEnumOption']['hiddenBoolFilterList'][] = "onlyExtensibleEnumOptionIds";

    }

    protected function defineClassificationViewForProduct(&$metadata)
    {
        if ($this->getConfig()->get('allowSingleClassificationForProduct', false)) {
            $metadata['entityDefs']['Product']['fields']['classifications']['view'] = "pim:views/product/fields/classifications-single";
        }
    }
}
