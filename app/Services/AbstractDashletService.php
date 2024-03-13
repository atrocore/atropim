<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\ORM\Repositories\RDB;
use Espo\Core\Services\Base;
use Espo\Services\DashletInterface;

abstract class AbstractDashletService extends Base implements DashletInterface
{
    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    protected function getPDO(): \PDO
    {
        return $this->getEntityManager()->getPDO();
    }

    protected function getRepository(string $entityType): RDB
    {
        return $this->getEntityManager()->getRepository($entityType);
    }
}
