<?php

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class AbstractRepository
 * @package Pim\Repositories
 */
class AbstractRepository extends Base
{
    /**
     * @var string
     */
    protected $ownership;

    /**
     * @var string
     */
    protected $ownershipRelation;

    /**
     * @var string
     */
    protected $assignedUserOwnership;

    /**
     * @var string
     */
    protected $ownerUserOwnership;

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        $this->changeOwnership($entity);
    }

    /**
     * @param Entity $entity
     */
    protected function changeOwnership(Entity $entity)
    {
        if ($entity->isAttributeChanged('assignedUserId') || $entity->isAttributeChanged('ownerUserId')) {
            $assignedUserOwnership = $this->getConfig()->get($this->assignedUserOwnership, '');
            $ownerUserOwnership = $this->getConfig()->get($this->ownerUserOwnership, '');

            if ($assignedUserOwnership == $this->ownership || $ownerUserOwnership == $this->ownership) {
                foreach ($entity->get($this->ownershipRelation) as $related) {
                    $toSave = false;

                    if ($assignedUserOwnership == $this->ownership
                        && ($related->get('assignedUserId') == null || $related->get('assignedUserId') == $entity->getFetched('assignedUserId'))) {
                        $related->set('assignedUserId', $entity->get('assignedUserId'));
                        $related->set('assignedUserName', $entity->get('assignedUserName'));
                        $toSave = true;
                    }

                    if ($ownerUserOwnership == $this->ownership
                        && ($related->get('ownerUserId') == null || $related->get('ownerUserId') == $entity->getFetched('ownerUserId'))) {
                        $related->set('ownerUserId', $entity->get('ownerUserId'));
                        $related->set('ownerUserName', $entity->get('ownerUserName'));
                        $toSave = true;
                    }

                    if ($toSave) {
                        $this->getEntityManager()->saveEntity($related, ['skipAll' => true]);
                    }
                }
            }
        }
    }
}
