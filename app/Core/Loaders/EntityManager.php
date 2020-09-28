<?php

declare(strict_types=1);

namespace Pim\Core\Loaders;

/**
 * Class EntityManager
 *
 * @author r.ratsun@gmail.com
 */
class EntityManager extends \Treo\Core\Loaders\EntityManager
{
    /**
     * @inheritdoc
     */
    protected function getEntityManagerClassName(): string
    {
        return \Pim\Core\ORM\EntityManager::class;
    }
}
