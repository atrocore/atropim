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
 * Class of Attribute
 */
class Attribute extends AbstractSelectManager
{
    /**
     * @inheritdoc
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $selectParams = parent::getSelectParams($params, $withAcl, $checkWherePermission);
        $types = implode("','", $this->getMetadata()->get('entityDefs.Attribute.fields.type.options', []));

        if (!isset($selectParams['customWhere'])) {
            $selectParams['customWhere'] = '';
        }
        // add filtering by attributes types
        $selectParams['customWhere'] .= " AND attribute.type IN ('{$types}')";

        return $selectParams;
    }

    /**
     * NotLinkedWithProduct filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProduct(&$result)
    {
        // prepare data
        $productId = (string)$this->getSelectCondition('notLinkedWithProduct');

        foreach ($this->createService('Product')->getAttributes($productId) as $row) {
            $result['whereClause'][] = [
                'id!=' => $row['attributeId']
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProductFamilyAttribute(array &$result)
    {
        // get filter data
        $data = (array)$this->getSelectCondition('notLinkedWithProductFamilyAttribute');

        if (isset($data['productFamilyId']) && isset($data['channelsIds'])) {
            $attributesIds = $this
                ->getEntityManager()
                ->getRepository('ProductFamilyAttribute')
                ->select(['attributeId'])
                ->where(
                    [
                        'channelId' => $data['channelsIds'],
                        'productFamilyId' => $data['productFamilyId'],
                        'scope' => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($attributesIds, 'attributeId')
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProductAttributeValue(array &$result)
    {
        // get filter data
        $data = (array)$this->getSelectCondition('notLinkedWithProductAttributeValue');

        if (isset($data['productId']) && isset($data['channelId'])) {
            $attributesIds = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['attributeId'])
                ->where(
                    [
                        'channelId' => $data['channelId'],
                        'productId' => $data['productId'],
                        'scope'     => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($attributesIds, 'attributeId')
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedProductAttributeValues(array &$result)
    {
        // prepare data
        $data = (array)$this->getSelectCondition('notLinkedProductAttributeValues');

        if (isset($data['productId']) && isset($data['scope'])) {
            // get linked to product attributes
            $attributes = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['attributeId'])
                ->where(
                    [
                        'productId' => $data['productId'],
                        'scope'     => $data['scope']
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($attributes, 'attributeId')
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedProductFamilyAttributes(array &$result)
    {
        // prepare data
        $data = (array)$this->getSelectCondition('notLinkedProductFamilyAttributes');

        if (isset($data['productFamilyId'])) {
            $result['whereClause'][] = [
                'id!=' => $this->getEntityManager()->getRepository('ProductFamily')->getLinkedAttributesIds($data['productFamilyId'])
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterUnitTypeDisabled(array &$result)
    {
        $unitAttributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->where(
                [
                    'type' => 'unit'
                ]
            )
            ->find()
            ->toArray();

        if (count($unitAttributes) > 0) {
            $result['whereClause'][] = [
                'id!=' => array_column($unitAttributes, 'id')
            ];
        }
    }

    protected function boolFilterFromAttributesTab(array &$result): void
    {
        $data = (array)$this->getSelectCondition('fromAttributesTab');

        if (isset($data['tabId'])) {
            $result['whereClause'][] = [
                'attributeTabId=' => empty($data['tabId']) ? null : $data['tabId']
            ];
        }
    }
}
