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

namespace Pim\Repositories;

use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class CatalogCategory extends \Atro\Core\Templates\Repositories\Relation
{
    private Category $categoryRepository;

    protected function beforeSave(Entity $entity, array $options = [])
    {
        $categoryId = $entity->get('categoryId');

        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
            if (!$this->getCategoryRepository()->isRoot($categoryId)) {
                throw new BadRequest($this->exception('onlyRootCategoryCanBeLinked'));
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $catalogId = $entity->get('catalogId');
        $categoryId = $entity->get('categoryId');

        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {

            foreach ($this->getCategoryRepository()->getChildrenRecursivelyArray($categoryId) as $childId) {
                $options['pseudoTransactionManager']->pushCreateEntityJob('CatalogCategory', ['categoryId' => $childId, 'catalogId' => $catalogId]);
            }
        }
        parent::afterSave($entity, $options);
    }

    public function getCategoryRepository()
    {
        if (empty($this->categoryRepository)) {
            $this->categoryRepository = $this->getEntityManager()->getRepository('Category');
        }
        return $this->categoryRepository;
    }

    public function isRootCategory(string $categoryId)
    {
        return $this->getCategoryRepository()->isRoot($categoryId);
    }


    protected function beforeRemove(Entity $entity, array $options = [])
    {
        $catalogId = $entity->get('catalogId');

        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
            if (!$this->isRootCategory($entity->get('categoryId'))) {
                throw new BadRequest($this->translate('onlyRootCategoryCanBeUnLinked', 'exceptions', 'Category'));
            }

            if ($this->getConfig()->get('behaviorOnCategoryTreeUnlinkFromCatalog', 'cascade') !== 'cascade') {
                if (!$this->getEntityManager()->getRepository('Catalog')->hasProducts($catalogId)) {
                    return;
                }

                $categoriesIds = $this->getCategoryRepository()->getChildrenRecursivelyArray($entity->get('categoryId'));
                $categoriesIds[] = $entity->get('categoryId');

                $records = $this->getConnection()->createQueryBuilder()
                    ->select('pc.id')
                    ->from('product_category', 'pc')
                    ->where('pc.deleted = :false')
                    ->andWhere('pc.category_id IN (:categoryIds)')
                    ->andWhere("pc.product_id IN (SELECT p.id FROM {$this->getConnection()->quoteIdentifier('product')} p WHERE p.catalog_id = :catalogId AND p.deleted = :false)")
                    ->setParameter('false', false, Mapper::getParameterType(false))
                    ->setParameter('categoryIds', $categoriesIds, Mapper::getParameterType($categoriesIds))
                    ->setParameter('catalogId', $catalogId, Mapper::getParameterType($catalogId))
                    ->setFirstResult(0)
                    ->setMaxResults(1)
                    ->fetchAllAssociative();

                if (!empty($records)) {
                    throw new BadRequest($this->exception('categoryCannotBeUnRelatedFromCatalog'));
                }
            }
        }

        parent::beforeRemove($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (empty($options['pseudoTransactionId']) && !empty($options['pseudoTransactionManager'])) {
            $childIds = $this->getCategoryRepository()->getChildrenRecursivelyArray($entity->get('categoryId'));

            $ids = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('catalog_category')
                ->where('catalog_id = :catalogId')
                ->andWhere('category_id in (:childIds)')
                ->setParameter('catalogId', $entity->get('catalogId'))
                ->setParameter('childIds', $childIds, Mapper::getParameterType($childIds))
                ->fetchFirstColumn();

            foreach ($ids as $id) {
                $options['pseudoTransactionManager']->pushDeleteEntityJob('CatalogCategory', $id);
            }

            foreach ($childIds as $childId) {
                $this->getConnection()->createQueryBuilder()
                    ->delete('product_category')
                    ->where('category_id = :childId')
                    ->andWhere('product_id IN (SELECT id FROM product WHERE catalog_id = :catalogId)')
                    ->setParameter('childId', $childId)
                    ->setParameter('catalogId', $entity->get('catalogId'))
                    ->executeQuery();
            }

        }

        parent::afterRemove($entity, $options);
    }

    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Category');
    }

    protected function translate(string $key, string $label = 'labels', string $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

}
