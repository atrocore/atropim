<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Pim\Entities\ProductFamilyAttribute;
use Treo\Core\EventManager\Event;

/**
 * Class ProductFamilyEntity
 *
 * @package Pim\Listeners
 * @author  r.ratsun@gmail.com
 */
class ProductFamilyEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if (!$this->isCodeValid($entity)) {
            throw new BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        $this->validRelationsWithProduct($entity->id);
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event): void
    {
        $this->removeProductFamilyAttribute($event);
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'productFamilyAttributes'
            && !empty($foreign = $event->getArgument('foreign'))
            && !is_string($foreign)) {
            $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->removeCollectionByProductFamilyAttribute($foreign->get('id'));
        }
    }

    /**
     * Validation ProductFamily relations Product
     *
     * @param string $id
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProduct(string $id): void
    {
        if ($this->hasProducts($id)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Product Family is used in products',
                    'exceptions',
                    'ProductFamily'
                )
            );
        }
    }

    /**
     * Has Products relations ProductFamily
     *
     * @param string $id
     *
     * @return bool
     */
    protected function hasProducts(string $id): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['productFamilyId' => $id])
            ->count();

        return !empty($count);
    }

    /**
     * @param Event $event
     */
    protected function removeProductFamilyAttribute(Event $event): void
    {
        /** @var ProductFamilyAttribute[] $productFamilyAttributes */
        $productFamilyAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['id'])
            ->where(['productFamilyId' => $event->getArgument('entity')->get('id')])
            ->find();
        
        foreach ($productFamilyAttributes as $productFamilyAttribute) {
            $this->getEntityManager()->removeEntity($productFamilyAttribute);
        }
    }
}
