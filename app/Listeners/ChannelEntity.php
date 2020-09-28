<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions;
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
        if (!$this->isCodeValid($event->getArgument('entity'))) {
            throw new Exceptions\BadRequest(
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
     */
    public function afterUnrelate(Event $event)
    {
        //set default value in isActive for channel after deleted link
        if(is_object($foreign = $event->getArgument('foreign'))
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
}
