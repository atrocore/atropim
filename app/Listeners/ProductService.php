<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Pim\Repositories\Product;
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

        if (!empty($data->_mainEntityId)) {
            $this
                ->getProductRepository()
                ->updateProductCategorySortOrder((string)$event->getArgument('id'), (string)$data->_mainEntityId, (int)$data->pcSorting, false);
        }

        if (!empty($data->_id)) {
            $this
                ->getProductRepository()
                ->updateProductCategorySortOrder((string)$event->getArgument('id'), (string)$data->_id, (int)$data->pcSorting);
        }
    }

    /**
     * @return Product
     */
    protected function getProductRepository(): Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }
}
