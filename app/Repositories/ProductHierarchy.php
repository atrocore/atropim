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
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class ProductHierarchy extends Relation
{
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->isAttributeChanged('mainChild') && !empty($entity->get('mainChild'))) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id')
                ->from('product_hierarchy')
                ->where('deleted = :false')
                ->andWhere('parent_id = :parentId')
                ->andWhere('main_child = :true')
                ->andWhere('id != :id')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('parentId', $entity->get('parentId'),  ParameterType::STRING)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('id', $entity->isNew() ? 'no-such-id' : $entity->get('id'), ParameterType::STRING)
                ->fetchAllAssociative();

            if (!empty($res)) {
                foreach ($res as $row) {
                    $parent = $this->get($row['id']);
                    $parent->set('mainChild', false);
                    $this->save($parent);
                }
            }
        }

        parent::beforeSave($entity, $options);
    }
}
