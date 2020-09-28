<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;

/**
 * Class AssociationEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class AssociationEntity extends AbstractEntityListener
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

        if (empty($entity->get('isActive')) && $this->hasProduct($entity, true)) {
            throw new BadRequest(
                $this->translate(
                    'You can not deactivate association with active product(s)',
                    'exceptions',
                    'Association'
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

        if ($this->hasProduct($entity)) {
            throw new BadRequest(
                $this->translate(
                    'Association is linked with product(s). Please, unlink product(s) first',
                    'exceptions',
                    'Association'
                )
            );
        }
    }

    /**
     * Is association used in product(s)
     *
     * @param Entity $entity
     * @param bool   $isActive
     *
     * @return bool
     */
    protected function hasProduct(Entity $entity, bool $isActive = false): bool
    {
        // prepare attribute id
        $associationId = $entity->get('id');

        $sql
            = "SELECT
                  COUNT(ap.id) as total
                FROM associated_product AS ap
                  JOIN product AS pm 
                    ON pm.id = ap.main_product_id AND pm.deleted = 0
                  JOIN product AS pr 
                    ON pr.id = ap.related_product_id AND pr.deleted = 0
                WHERE ap.deleted = 0 AND ap.association_id = '{$associationId}'";

        if ($isActive) {
            $sql .= " AND (pm.is_active=1 OR pr.is_active=1)";
        }

        // execute
        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        // get data
        $data = $sth->fetch(\PDO::FETCH_ASSOC);

        return !empty($data['total']);
    }
}
