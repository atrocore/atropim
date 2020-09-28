<?php

namespace Pim\Entities;

use Espo\ORM\EntityCollection;

class Channel extends \Espo\Core\Templates\Entities\Base
{
    protected $entityType = "Channel";

    /**
     * Get channel products
     *
     * @param array $select
     *
     * @return EntityCollection|null
     */
    public function getProducts(array $select = []): ?EntityCollection
    {
        return $this
            ->getEntityManager()
            ->getRepository($this->getEntityType())
            ->getProducts($this->get('id'), $select);
    }

    /**
     * Get channel products ids
     *
     * @return array
     */
    public function getProductsIds(): array
    {
        return $this
            ->getEntityManager()
            ->getRepository($this->getEntityType())
            ->getProductsIds($this->get('id'));
    }
}
