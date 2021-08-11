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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of AttributeGroup
 */
class AttributeGroup extends AbstractSelectManager
{

    /**
     * @param array $result
     */
    protected function boolFilterWithNotLinkedAttributesToProduct(array &$result)
    {
        // get product attributes
        $productAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['attributeId'])
            ->where([
                'productId' => (string)$this->getSelectCondition('withNotLinkedAttributesToProduct'),
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        if (count($productAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($productAttributes)
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterWithNotLinkedAttributesToProductFamily(array &$result)
    {
        // get product family attributes
        $productFamilyAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['attributeId'])
            ->where([
                'productFamilyId' => (string)$this->getSelectCondition('withNotLinkedAttributesToProductFamily'),
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        if (count($productFamilyAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($productFamilyAttributes)
            ];
        }
    }

    /**
     * Get attributeGroups with not linked all related attributes to product or productFamily
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function getNotLinkedAttributeGroups(array $attributes): array
    {
        // prepare result
        $result = [];

        // get all attribute groups
        $attributeGroups = $this
            ->getEntityManager()
            ->getRepository('AttributeGroup')
            ->select(['id'])
            ->find();

        foreach ($attributeGroups as $attributeGroup) {
            $attr = $attributeGroup->get('attributes')->toArray();

            if (!empty(array_diff(
                array_column($attr, 'id'),
                array_column($attributes, 'attributeId')
            ))) {
                $result[] = $attributeGroup->get('id');
            }
        }

        return $result;
    }

    protected function boolFilterFromAttributesTab(array &$result): void
    {
        $data = (array)$this->getSelectCondition('fromAttributesTab');

        if (isset($data['tabId'])) {
            $attributes = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->select(['attributeGroupId'])
                ->where([
                    'attributeTabId'      => empty($data['tabId']) ? null : $data['tabId'],
                    'attributeGroupId !=' => null
                ])
                ->find();

            $result['whereClause'][] = [
                'id' => array_unique(array_column($attributes->toArray(), 'attributeGroupId'))
            ];
        }
    }
}
