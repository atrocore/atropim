<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class ExportProfileEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ExportProfileEntity extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if ($entity->isNew() && $entity->get('type') == 'productImage') {
            $entity->set('isHeaderRow', true);
        }
    }
}
