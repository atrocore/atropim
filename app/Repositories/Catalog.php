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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Hierarchy;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class Catalog extends Hierarchy
{
    public function getProductsCount(Entity $catalog): int
    {
        return $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->where(['catalogId' => $catalog->get('id')])
            ->count();
    }

    public function hasProducts(string $catalogId): bool
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->from($this->getConnection()->quoteIdentifier('product'), 'p')
            ->where('p.catalog_id = :catalogId')
            ->andWhere('deleted = :false')
            ->setParameter('catalogId', $catalogId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAssociative();

        return !empty($res);
    }

    public function getProductsIds(string $catalogId): array
    {
        $res = $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->from($this->getConnection()->quoteIdentifier('product'), 'p')
            ->where('p.catalog_id = :catalogId')
            ->andWhere('deleted = :false')
            ->setParameter('catalogId', $catalogId)
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->fetchAllAssociative();

        return array_column($res, 'id');
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->update($connection->quoteIdentifier('product'), 'p')
            ->set('deleted', ':false')
            ->where('p.catalog_id = :id')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $entity->get('id'))
            ->executeQuery();
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        parent::beforeSave($entity, $options);
    }

}
