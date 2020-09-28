<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\EventManager\Event;

/**
 * Class AttributeGroupEntity
 *
 * @author r.ratsun@gmail.com
 */
class AttributeGroupEntity extends AbstractEntityListener
{
    /**
     * Before save action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        if (!$this->isCodeValid($event->getArgument('entity'))) {
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
     * Before remove action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        if (count($event->getArgument('entity')->get('attributes')) > 0) {
            throw new BadRequest(
                $this->translate(
                    'Attribute group is linked with attribute(s). Please, unlink attribute(s) first',
                    'exceptions',
                    'AttributeGroup'
                )
            );
        }
    }
}
