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

    public function relateCategories(Entity $entity, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->relateCatalogs($category, $entity, null, $options);
    }

    public function unrelateCategories(Entity $entity, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->unrelateCatalogs($category, $entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $connection = $this->getEntityManager()->getConnection();

        $connection->createQueryBuilder()
            ->update($connection->quoteIdentifier('product'), 'p')
            ->set('deleted', ':false')
            ->where('p.catalog_id = :id')
            ->setParameter('false', false, Mapper::getParameterType('false'))
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

    /**
     * @inheritDoc
     */
    protected function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'products') {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->getEntityManager()->getRepository('Product')->{"onCatalog{$mode}Change"}($foreign, $entity);
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    protected function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'products') {
            $mode = ucfirst($this->getConfig()->get('behaviorOnCatalogChange', 'cascade'));
            $this->getEntityManager()->getRepository('Product')->{"onCatalog{$mode}Change"}($foreign, null);
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }
}
