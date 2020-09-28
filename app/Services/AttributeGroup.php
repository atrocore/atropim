<?php

namespace Pim\Services;

class AttributeGroup extends \Espo\Core\Templates\Services\Base
{
    /**
     * Get sorted linked attributes
     *
     * @param $attributeGroupId
     *
     * @return array
     */
    public function findLinkedEntitiesAttributes(string $attributeGroupId): array
    {
        $attributesTypes =  $this->getMetadata()->get('entityDefs.Attribute.fields.type.options', []);

        $result = $this->getEntityManager()
            ->getRepository('Attribute')
            ->distinct()
            ->join('attributeGroup')
            ->where(['attributeGroupId' => $attributeGroupId, 'type' => $attributesTypes])
            ->order('sortOrder', 'ASC')
            ->find()
            ->toArray();

        return [
            'total' => count($result),
            'list' => $result
        ];
    }
}
