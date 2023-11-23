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

namespace Pim\Entities;

use Atro\Core\Templates\Entities\Hierarchy;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Core\Exceptions\Error;

/**
 * Entity Category
 */
class Category extends Hierarchy
{
    public bool $recursiveSave = false;

    /**
     * @var string
     */
    protected $entityType = "Category";

    /**
     * @return Entity
     * @throws Error
     */
    public function getRoot(): Entity
    {
        // validation
        $this->isEntity();

        $routeIds = array_keys($this->getEntityManager()->getRepository('Category')->getHierarchyRoute($this->get('id')));

        return (isset($routeIds[0])) ? $this->getEntityManager()->getEntity('Category', $routeIds[0]) : $this;
    }

    public function getParentsIds(): array
    {
        // validation
        $this->isEntity();

        return array_keys($this->getEntityManager()->getRepository('Category')->getHierarchyRoute($this->get('id')));
    }

    /**
     * @return bool
     * @throws Error
     */
    public function hasChildren(): bool
    {
        // validation
        $this->isEntity();

        $children = $this->get('children');

        return !empty($children) && count($children) > 0;
    }

    /**
     * @return EntityCollection
     * @throws Error
     */
    public function getTreeProducts(): EntityCollection
    {
        // validation
        $this->isEntity();

        // prepare where
        $where = [
            'categories.id' => [$this->get('id')]
        ];

        $childIds = $this->getEntityManager()->getRepository('Category')->getChildrenRecursivelyArray($this->get('id'));

        if (count($childIds) > 0) {
            $where['categories.id'] = array_merge($where['categories.id'], $childIds);
        }

        return $this
            ->getEntityManager()
            ->getRepository('Product')
            ->distinct()
            ->join('categories')
            ->where($where)
            ->find();
    }

    /**
     * @return bool
     * @throws Error
     */
    protected function isEntity(): bool
    {
        if (empty($id = $this->get('id'))) {
            throw new Error('Category is not exist');
        }

        return true;
    }
}
