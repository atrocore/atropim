<?php

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

/**
 * Class ProductFamily
 *
 * @author r.ratsun@gmail.com
 */
class ProductFamily extends Base
{
    /**
     * Get count not empty product family attributes
     *
     * @param string $productFamilyId
     * @param string $attributeId
     *
     * @return int
     */
    public function getLinkedProductAttributesCount(string $productFamilyId, string $attributeId): int
    {
        // prepare result
        $count = 0;

        // if not empty productFamilyId and attributeId
        if (!empty($productFamilyId) && !empty($attributeId)) {
            // get count products
            $count = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->where(
                    [
                        'productFamilyId' => $productFamilyId,
                        'attributeId'     => $attributeId,
                        'value!='         => ['null', '', 0, '0', '[]']
                    ]
                )
                ->count();
        }

        return $count;
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }

    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateProductFamilyAttributes(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($productFamilyAttributes = $duplicatingEntity->get('productFamilyAttributes')->toArray())) {
            // get service
            $service = $this->getInjection('serviceFactory')->create('ProductFamilyAttribute');

            foreach ($productFamilyAttributes as $productFamilyAttribute) {
                // prepare data
                $data = $service->getDuplicateAttributes($productFamilyAttribute['id']);
                $data->productFamilyId = $entity->get('id');

                // create entity
                $service->createEntity($data);
            }
        }
    }
}
