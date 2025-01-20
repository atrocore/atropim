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
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;

class CategoryHierarchy extends \Atro\Core\Templates\Repositories\Relation
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        //rebuild tree
        if($entity->isNew() || $entity->isAttributeChanged('parentId')){
            $category = $this->getCategoryRepository()->get($entity->get('entityId'));
            $this->updateCategoryTree($category);
        }

        parent::afterSave($entity, $options);
    }

    protected function updateCategoryTree(Entity $entity): void
    {
        $this->getCategoryRepository()->updateRoute($entity);

        $ids = $this->getCategoryRepository()->getChildrenRecursivelyArray($entity->get('id'));

        foreach ($ids as $id) {
            $child = $this->getCategoryRepository()->get($id);
            $this->getCategoryRepository()->updateRoute($child);
        }
    }

    protected function getCategoryRepository() : Category {
        return $this->getEntityManager()->getRepository('Category');
    }

}
