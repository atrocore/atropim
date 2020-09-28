<?php

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class AssociatedProduct
 *
 * @author r.ratsun@gmail.com
 */
class AssociatedProduct extends Base
{
    /**
     * @inheritDoc
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (empty($entity->get('name'))) {
            $entity->set('name', $entity->get('associationName'));
        }
    }
}
