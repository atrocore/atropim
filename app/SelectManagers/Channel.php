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
    protected function boolFilterNotLinkedWithAttributesInClassification(array &$result)
    {
        $data = (array)$this->getSelectCondition('notLinkedWithAttributesInClassification');

        if (isset($data['classificationId']) && isset($data['attributeId'])) {
            $channels = $this
                ->getEntityManager()
                ->getRepository('Channel')
                ->select(['id'])
                ->distinct()
                ->join(['classificationAttributes'])
                ->where(
                    [
                        'classificationAttributes.attributeId'     => $data['attributeId'],
                        'classificationAttributes.classificationId' => $data['classificationId']
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
