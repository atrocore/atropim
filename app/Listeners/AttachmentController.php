<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class CatalogController
 *
 * @author r.ratsun r.ratsun@gmail.com
 */
class AttachmentController extends AbstractListener
{
    /**
     * @var string
     */
    protected $entityType = 'Attachment';

    /**
     * @param Event $event
     */
    public function beforeActionCreate(Event $event)
    {
        $data = $event->getArgument('data');

        if ($data->relatedType == 'ProductAttributeValue') {
            $data->field = 'image';
        }

       $event->setArgument('data', $data);
    }
}
