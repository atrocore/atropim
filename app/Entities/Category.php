<?php
declare(strict_types=1);

namespace Pim\Entities;

use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Core\Exceptions\Error;

/**
 * Entity Category
 *
 * @author r.ratsun@gmail.com
 */
class Category extends \Espo\Core\Templates\Entities\Base
{
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

        $categoryRoute = explode('|', $this->get('categoryRoute'));

        return (isset($categoryRoute[1])) ? $this->getEntityManager()->getEntity('Category', $categoryRoute[1]) : $this;
    }

    /**
     * @return bool
     * @throws Error
     */
    public function hasChildren(): bool
    {
        // validation
        $this->isEntity();

        $count = $this
            ->getEntityManager()
            ->getRepository('Category')
            ->where(['categoryParentId' => $this->get('id')])
            ->count();

        return !empty($count);
    }

    /**
     * @return EntityCollection
     * @throws Error
     */
    public function getChildren(): EntityCollection
    {
        // validation
        $this->isEntity();

        return $this
            ->getEntityManager()
            ->getRepository('Category')
            ->where(['categoryRoute*' => "%|" . $this->get('id') . "|%"])
            ->find();
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

        $categoryChildren = $this->getChildren();

        if (count($categoryChildren) > 0) {
            $where['categories.id'] = array_merge($where['categories.id'], array_column($categoryChildren->toArray(), 'id'));
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
