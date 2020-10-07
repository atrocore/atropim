<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;

/**
 * Class ProductService
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductService extends AbstractEntityListener
{
    /**
     * @param Event $event
     */
    public function beforeUpdateEntity(Event $event)
    {
        $data = $event->getArgument('data');
        if (!empty($data->_id)) {
            $this
                ->getEntityManager()
                ->getRepository('Product')
                ->updateProductCategorySortOrder((string)$event->getArgument('id'), (string)$data->_id, (int)$data->pcSorting);
        }
    }
}
