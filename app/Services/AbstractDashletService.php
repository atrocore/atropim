<?php
declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Repositories\Base as BaseRepository;
use Espo\Core\Services\Base;
use Treo\Services\DashletInterface;

/**
 * Class AbstractDashletService
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
abstract class AbstractDashletService extends Base implements DashletInterface
{
    /**
     * Get PDO
     *
     * @return \PDO
     */
    protected function getPDO(): \PDO
    {
        return $this->getEntityManager()->getPDO();
    }

    /**
     * Get Repository
     *
     * @param $entityType
     *
     * @return BaseRepository
     */
    protected function getRepository(string $entityType): BaseRepository
    {
        return $this->getEntityManager()->getRepository($entityType);
    }
}
