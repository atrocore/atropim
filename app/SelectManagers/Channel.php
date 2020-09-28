<?php

declare(strict_types=1);

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class Channel
 *
 * @author r.ratsun@gmail.com
 */
class Channel extends AbstractSelectManager
{
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
                ->where([
                    'productAttributeValues.attributeId' => $data['attributeId'],
                    'productAttributeValues.productId' => $data['productId']
                ])
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
                ->where([
                    'productFamilyAttributes.attributeId' => $data['attributeId'],
                    'productFamilyAttributes.productFamilyId' => $data['productFamilyId']
                ])
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
            ->where([
                'productCategories.productId' => $data['productId'],
                'productCategories.categoryId' => $data['categoryId']
            ])
            ->find()
            ->toArray();

        if (count($productCategories) > 0) {
            $result['whereClause'][] = [
                'id!=' => array_column($productCategories, 'id')
            ];
        }
    }
}
