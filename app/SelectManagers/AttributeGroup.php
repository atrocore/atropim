<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
    protected function boolFilterWithNotLinkedAttributesToClassification(array &$result)
    {
        // get product family attributes
        $classificationAttributes = $this
            ->getEntityManager()
            ->getRepository('ClassificationAttribute')
            ->select(['attributeId'])
            ->where([
                'classificationId' => (string)$this->getSelectCondition('withNotLinkedAttributesToClassification'),
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        if (count($classificationAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($classificationAttributes)
            ];
        }
    }

    /**
     * Get attributeGroups with not linked all related attributes to product or classification
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
