<?php
declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Entities\Association;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class AssociatedProductEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class AssociatedProductEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->get('mainProductId') == $entity->get('relatedProductId')) {
            throw new BadRequest($this->exception('itselfAssociation'));
        }

        if ($entity->isNew()) {
            if (!$this->isUnique($entity)) {
                throw new BadRequest($this->exception('productAssociationAlreadyExists'));
            }

            if (!empty($entity->massRelateAction)) {
                $this->createBackwardAssociation($entity);
            }
        }
    }

    /**
     * @param Event $event
     */
    public function beforeRemove(Event $event)
    {
        /** @var Entity $associatedProduct */
        $associatedProduct = $event->getArgument('entity');

        /** @var string $backwardAssociationId */
        $backwardAssociationId = $associatedProduct->get('backwardAssociationId');

        if (!empty($backwardAssociationId)) {
            $backwards = $associatedProduct->get('backwardAssociation')->get('associatedProducts');
            if ($backwards->count() > 0) {
                foreach ($backwards as $backward) {
                    if ($backward->get('mainProductId') == $associatedProduct->get('relatedProductId')
                        && $backward->get('relatedProductId') == $associatedProduct->get('mainProductId')
                        && $backward->get('associationId') == $backwardAssociationId) {
                        $this->getEntityManager()->removeEntity($backward);
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        $exist = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->select(['id'])
            ->where(
                [
                    'associationId'    => $entity->get('associationId'),
                    'mainProductId'    => $entity->get('mainProductId'),
                    'relatedProductId' => $entity->get('relatedProductId')
                ]
            )
            ->findOne();

        return empty($exist);
    }

    /**
     * @param Entity $entity
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function createBackwardAssociation(Entity $entity)
    {
        /** @var Association $association */
        $association = $this->getEntityManager()->getEntity('Association', $entity->get('associationId'));

        if (!empty($association) && !empty($backwardAssociationId = $association->get('backwardAssociationId'))) {
            $entity->set('backwardAssociationId', $backwardAssociationId);

            $backwardEntity = $this->getEntityManager()->getEntity('AssociatedProduct');
            $backwardEntity->set("associationId", $backwardAssociationId);
            $backwardEntity->set("mainProductId", $entity->get('relatedProductId'));
            $backwardEntity->set("relatedProductId", $entity->get('mainProductId'));

            $this->getEntityManager()->saveEntity($backwardEntity);
        }
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getLanguage()->translate($key, 'exceptions', 'Product');
    }
}
