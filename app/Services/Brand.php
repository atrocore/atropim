<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

class Brand extends Base
{
    protected function afterDeleteEntity(Entity $entity)
    {
        parent::afterDeleteEntity($entity);

        $this->unlinkBrand([$entity->get('id')]);
    }

    protected function unlinkBrand(array $ids): bool
    {
        $result = false;

        if (!empty($ids)) {
            $connection = $this->getEntityManager()->getConnection();

            $connection->createQueryBuilder()
                ->update($connection->quoteIdentifier('product'), 'p')
                ->set('brand_id', null)
                ->where('p.brand_id IN (:ids)')
                ->setParameter('ids', $ids, Mapper::getParameterType($ids))
                ->executeQuery();

            $result = true;
        }

        return $result;
    }
}
