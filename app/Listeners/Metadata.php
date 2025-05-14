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
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractListener;

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

        $data = $this->prepareClassificationAttributeMetadata($data);

        $this->addClassificationToEntity($data);

        if ($this->getConfig()->get('behaviorOnCategoryDelete', 'cascade') == 'cascade') {
            $data['clientDefs']['Category']['deleteConfirmation'] = 'Category.messages.categoryRemoveConfirm';
        }

        if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') == 'cascade') {
            $data['clientDefs']['Catalog']['relationshipPanels']['categories']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
            $data['clientDefs']['Category']['relationshipPanels']['catalogs']['unlinkConfirm'] = 'Category.messages.categoryCatalogUnlinkConfirm';
        }

        $this->addOnlyExtensibleEnumOptionForCABoolFilter($data);

        $event->setArgument('data', $data);
    }

    protected function addClassificationToEntity(array &$data): void
    {
        foreach ($data['scopes'] ?? [] as $scope => $scopeDefs) {
            if (!empty($scopeDefs['hasAttribute']) && !empty($scopeDefs['hasClassification'])) {
                $data['entityDefs'][$scope]['fields']['classifications'] = [
                    "type" => "linkMultiple"
                ];
                $data['entityDefs'][$scope]['links']['classifications'] = [
                    "type"         => "hasMany",
                    "foreign"      => Util::pluralize(lcfirst($scope)),
                    "relationName" => "{$scope}Classification",
                    "entity"       => "Classification"
                ];

                $data['entityDefs']['Classification']['fields'][Util::pluralize(lcfirst($scope))] = [
                    "type" => "linkMultiple"
                ];

                $data['entityDefs']['Classification']['links'][Util::pluralize(lcfirst($scope))] = [
                    "type"         => "hasMany",
                    "foreign"      => 'classifications',
                    "relationName" => "{$scope}Classification",
                    "entity"       => "$scope"
                ];

                $data['scopes']["{$scope}Classification"]['classificationForEntity'] = $scope;
            }
        }
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

    protected function addOnlyExtensibleEnumOptionForCABoolFilter(&$metadata)
    {
        $metadata['clientDefs']['ExtensibleEnumOption']['boolFilterList'][] = "onlyExtensibleEnumOptionIds";
        $metadata['clientDefs']['ExtensibleEnumOption']['hiddenBoolFilterList'][] = "onlyExtensibleEnumOptionIds";
    }
}
