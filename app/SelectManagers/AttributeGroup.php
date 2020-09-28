<?php

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of AttributeGroup
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
}
