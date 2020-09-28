<?php
declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Catalog repository
 *
 * @author r.ratsun@gmail.com
 */
class Catalog extends Base
{
    /**
     * @inheritDoc
     */
    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        /** @var string $id */
        $id = $entity->get('id');

        // remove catalog products
        $this->getEntityManager()->nativeQuery("UPDATE product SET deleted=1 WHERE catalog_id='$id'");
    }
}
