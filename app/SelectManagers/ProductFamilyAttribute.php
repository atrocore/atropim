<?php

declare(strict_types=1);

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class ProductFamilyAttribute
 *
 * @author r.ratsun@gmail.com
 */
class ProductFamilyAttribute extends AbstractSelectManager
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
        $selectParams['customWhere'] .= " 
            AND product_family_attribute.attribute_id IN (SELECT id 
                                                            FROM attribute 
                                                            WHERE type IN ('{$types}') AND deleted=0)";

        return $selectParams;
    }
    /**
     * @param array $result
     */
    protected function boolFilterLinkedWithAttributeGroup(array &$result)
    {
        $data = (array)$this->getSelectCondition('linkedWithAttributeGroup');

        if (isset($data['productFamilyId'])) {
            // prepare data
            $ids = [$data['productFamilyId']];
            $attributeGroupId = ($data['attributeGroupId'] != '') ? $data['attributeGroupId'] : null;

            $result['whereClause'][] = [
                'id' => $this->getEntityManager()->getRepository('ProductFamily')->getLinkedWithAttributeGroup($ids, $attributeGroupId)
            ];
        }
    }
}
