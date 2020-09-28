<?php

declare(strict_types=1);

namespace Pim\Core\ORM;

use Pim\ORM\DB\MysqlMapper;
use Pim\ORM\DB\Query\Mysql;

/**
 * Class of EntityManager
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class EntityManager extends \Treo\Core\ORM\EntityManager
{

    /**
     * @inheritdoc
     */
    public function getQuery()
    {
        if (empty($this->query)) {
            $this->query = new Mysql($this->getPDO(), $this->entityFactory);
        }

        return $this->query;
    }
}
