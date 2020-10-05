<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions;
use Espo\ORM\Entity;
use Pim\Entities\Channel;
use Treo\Core\EventManager\Event;

/**
 * Class ChannelEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ChannelEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        /** @var Channel $entity */
        $entity = $event->getArgument('entity');

        if (!$this->isCodeValid($entity)) {
            throw new Exceptions\BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
        }

        // cascade products relating
        $this->cascadeProductsRelating($entity);
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        //set default value in isActive for channel after deleted link
        if (is_object($foreign = $event->getArgument('foreign'))
            && isset($foreign->getRelations()['channels']['additionalColumns']['isActive'])) {
            $dataEntity = new \StdClass();
            $dataEntity->entityName = $foreign->getEntityName();
            $dataEntity->entityId = $foreign->get('id');
            $dataEntity->value = (int)!empty($foreign->getRelations()['channels']['additionalColumns']['isActive']['default']);

            $this
                ->getService('Channel')
                ->setIsActiveEntity($event->getArgument('foreign')->get('id'), $dataEntity, true);
        }
    }

    /**
     * @param Entity $entity
     *
     * @throws Exceptions\Error
     */
    protected function cascadeProductsRelating(Entity $entity)
    {
        if ($entity->isAttributeChanged('categoryId')) {
            /** @var \Pim\Repositories\Channel $channelRepository */
            $channelRepository = $this->getEntityManager()->getRepository('Channel');

            // unrelate prev
            if (!empty($entity->getFetched('categoryId'))) {
                $channelRepository->cascadeProductsRelating($entity->getFetched('categoryId'), $entity, true);
            }

            // relate new
            if (!empty($entity->get('categoryId'))) {
                $channelRepository->cascadeProductsRelating($entity->get('categoryId'), $entity);
            }
        }
    }
}
