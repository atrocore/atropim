<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Pim\Controllers\Attribute;
use Treo\Core\EventManager\Event;

/**
 * Class AttributeEntity
 *
 * @author r.ratsun@gmail.com
 */
class AttributeEntity extends AbstractEntityListener
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
            throw new BadRequest($this->translate('Code is invalid', 'exceptions', 'Global'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest(
                $this->translate('You can\'t change field of Type in Attribute', 'exceptions', 'Attribute'));
        }
    }
}
