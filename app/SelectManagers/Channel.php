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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class Channel
 */
class Channel extends AbstractSelectManager
{
    /**
     * @param $result
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterProductChannels(&$result)
    {
        // get product id
        $productId = (string)$this->getSelectCondition('productChannels');

        if (!empty($productId)) {
            $product = $this->getEntityManager()->getEntity('Product', $productId);
            if (!empty($product)) {
                $result['whereClause'][] = [
                    'id' => array_column($product->get('channels')->toArray(), 'id')
                ];
            }
        }
    }

    /**
     * @param $result
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterNotLinkedWithProduct(&$result)
    {
        // get product id
        $productId = (string)$this->getSelectCondition('notLinkedWithProduct');

        if (!empty($productId)) {
            $product = $this->getEntityManager()->getEntity('Product', $productId);
            if (!empty($product)) {
                $result['whereClause'][] = [
                    'id!=' => array_column($product->get('channels')->toArray(), 'id')
                ];
            }
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithProductFamilyAttribute(array &$result)
    {
        // get filter data
        $data = (array)$this->getSelectCondition('notLinkedWithProductFamilyAttribute');

        if (isset($data['productFamilyId']) && isset($data['attributeId'])) {
            $channelsIds = $this
                ->getEntityManager()
                ->getRepository('ProductFamilyAttribute')
                ->select(['channelId'])
                ->where(
                    [
                        'attributeId'     => $data['attributeId'],
                        'productFamilyId' => $data['productFamilyId'],
                        'scope'           => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($channelsIds, 'channelId')
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

        if (isset($data['productId']) && isset($data['attributeId'])) {
            $channelsIds = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['channelId'])
                ->where(
                    [
                        'attributeId' => $data['attributeId'],
                        'productId'   => $data['productId'],
                        'scope'       => 'Channel',
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => array_column($channelsIds, 'channelId')
            ];
        }
    }

    /**
     * NotLinkedWithPriceProfile filter
     *
     * @param array $result
     */
    protected function boolFilterNotLinkedWithPriceProfile(&$result)
    {
        if (!empty($priceProfileId = (string)$this->getSelectCondition('notLinkedWithPriceProfile'))) {
            // get channel related with product
            $channel = $this->getEntityManager()
                ->getRepository('Channel')
                ->distinct()
                ->join('priceProfiles')
                ->where(['priceProfiles.id' => $priceProfileId])
                ->find();

            // set filter
            foreach ($channel as $row) {
                $result['whereClause'][] = [
                    'id!=' => $row->get('id')
                ];
            }
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithAttributesInProduct(array &$result)
    {
        $data = (array)$this->getSelectCondition('notLinkedWithAttributesInProduct');

        if (isset($data['productId']) && isset($data['attributeId'])) {
            $channels = $this
                ->getEntityManager()
                ->getRepository('Channel')
                ->select(['id'])
                ->distinct()
                ->join(['productAttributeValues'])
                ->where(
                    [
                        'productAttributeValues.attributeId' => $data['attributeId'],
                        'productAttributeValues.productId'   => $data['productId']
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => !empty($channels) ? array_column($channels, 'id') : []
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedWithAttributesInProductFamily(array &$result)
    {
        $data = (array)$this->getSelectCondition('notLinkedWithAttributesInProductFamily');

        if (isset($data['productFamilyId']) && isset($data['attributeId'])) {
            $channels = $this
                ->getEntityManager()
                ->getRepository('Channel')
                ->select(['id'])
                ->distinct()
                ->join(['productFamilyAttributes'])
                ->where(
                    [
                        'productFamilyAttributes.attributeId'     => $data['attributeId'],
                        'productFamilyAttributes.productFamilyId' => $data['productFamilyId']
                    ]
                )
                ->find()
                ->toArray();

            $result['whereClause'][] = [
                'id!=' => !empty($channels) ? array_column($channels, 'id') : []
            ];
        }
    }

    /**
     * @param $result
     */
    protected function boolFilterNotLinkedWithCategoriesInProduct(&$result)
    {
        $data = $this->getSelectCondition('notLinkedWithCategoriesInProduct');

        $productCategories = $this
            ->getEntityManager()
            ->getRepository('Channel')
            ->distinct()
            ->join(['productCategories'])
            ->select(['id'])
            ->where(
                [
                    'productCategories.productId'  => $data['productId'],
                    'productCategories.categoryId' => $data['categoryId']
                ]
            )
            ->find()
            ->toArray();

        if (count($productCategories) > 0) {
            $result['whereClause'][] = [
                'id!=' => array_column($productCategories, 'id')
            ];
        }
    }
}
