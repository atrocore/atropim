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

use Atro\Core\Templates\Repositories\Relation;
use Atro\Core\Utils\Util;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ProductCategory extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        $category = $this->getEntityManager()->getRepository('Category')->get($entity->get('categoryId'));

        $productField = $this->getRelatedLink('Product');
        $idColumn = Util::toUnderScore($productField . 'Id');

        if ($entity->isAttributeChanged('mainCategory') && !empty($entity->get('mainCategory'))) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('pc.id, pc.category_id, c.routes')
                ->from(Util::toUnderScore(lcfirst($this->entityName)), 'pc')
                ->innerJoin('pc', 'category', 'c', 'c.id = pc.category_id AND c.deleted = :false')
                ->where('pc.deleted = :false')
                ->andWhere("pc.$idColumn = :productId")
                ->andWhere('pc.main_category = :true')
                ->andWhere('pc.id != :id')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('productId', $entity->get($productField . 'Id'))
                ->setParameter('id', $entity->isNew() ? 'no-such-id' : $entity->get('id'))
                ->fetchAllAssociative();

            if (!empty($res[0])) {
                $root = $this->getCategoryRoot($category->get('id'), $category->get('routes'));
                foreach ($res as $row) {
                    $rowRoot = $this->getCategoryRoot($row['category_id'], json_decode($row['routes'], true));
                    if ($root === $rowRoot) {
                        // remove mainCategory from productCategory
                        $pc = $this->get($row['id']);
                        $pc->set('mainCategory', false);
                        $this->save($pc);
                    }
                }
            }
        }

        $this->getProductRepository()->isProductCanLinkToNonLeafCategory($entity->get('categoryId'));
        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $productField = $this->getRelatedLink('Product');
        $this->getProductRepository()
            ->updateProductCategorySortOrder($entity->get($productField . 'Id'), $entity->get('categoryId'));

        parent::afterSave($entity, $options);
    }

    protected function getCategoryRoot(string $id, array $routes): string
    {
        foreach ($routes as $route) {
            $route = explode('|', $route);
            array_shift($route);

            return array_shift($route);
        }

        return $id;
    }

    protected function getProductRepository(): Product
    {
        $productField = $this->getRelatedLink('Product');
        return $this->getEntityManager()->getRepository($this->getMetadata()->get(['entityDefs', $this->entityName, 'links', $productField, 'entity']));
    }
}
