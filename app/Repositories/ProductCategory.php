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

namespace Pim\Repositories;

use Atro\Core\Templates\Repositories\Relation;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class ProductCategory extends Relation
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('mainCategory') && !empty($entity->get('mainCategory'))) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('pc.id, pc.category_id, c.category_route')
                ->from('product_category', 'pc')
                ->innerJoin('pc', 'category', 'c', 'c.id = pc.category_id AND c.deleted = :false')
                ->where('pc.deleted = :false')
                ->andWhere('pc.product_id = :productId')
                ->andWhere('pc.main_category = :true')
                ->andWhere('pc.id != :id')
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('productId', $entity->get('productId'))
                ->setParameter('id', $entity->isNew() ? 'no-such-id' : $entity->get('id'))
                ->fetchAllAssociative();

            if (!empty($res[0])) {
                $category = $this->getEntityManager()->getRepository('Category')->get($entity->get('categoryId'));
                $root = $this->getCategoryRoot($category->get('id'), (string)$category->get('categoryRoute'));
                foreach ($res as $row) {
                    $rowRoot = $this->getCategoryRoot($row['category_id'], (string)$row['category_route']);
                    if ($root === $rowRoot) {
                        // remove main category from others
                        $pc = $this->get($row['id']);
                        $pc->set('mainCategory', false);
                        $this->save($pc);
                    }
                }
            }
        }

        parent::beforeSave($entity, $options);
    }

    protected function getCategoryRoot(string $id, string $categoryRoute): string
    {
        $root = $id;
        if (!empty($categoryRoute)) {
            $route = explode('|', $categoryRoute);
            array_shift($route);
            $root = array_shift($route);
        }

        return $root;
    }
}
