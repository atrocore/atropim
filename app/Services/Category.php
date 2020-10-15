<?php

namespace Pim\Services;

use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

/**
 * Service of Category
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Category extends AbstractService
{
    /**
     * @var array
     */
    protected $mandatorySelectAttributeList = ['categoryRoute'];

    /**
     * @var array
     */
    private $roots = [];

    /**
     * Get category entity
     *
     * @param string $id
     *
     * @return array
     * @throws Forbidden
     */
    public function getEntity($id = null)
    {
        // call parent
        $entity = parent::getEntity($id);

        if (!empty($entity)) {
            /** @var array $channels */
            $channels = $this->getRootChannels($entity)->toArray();

            // set hasChildren param
            $entity->set('hasChildren', $entity->hasChildren());
            $entity->set('channelsIds', array_column($channels, 'id'));
            $entity->set('channelsNames', array_column($channels, 'name', 'id'));
        }

        return $entity;
    }

    /**
     * @inheritDoc
     */
    public function findEntities($params)
    {
        $result = parent::findEntities($params);

        /**
         * Set channels to children categories
         */
        if (!empty($result['total'])) {
            $roots = [];
            foreach ($result['collection'] as $category) {
                if (empty($category->get('channelsIds'))) {
                    /** @var array $channels */
                    $channels = $this->getRootChannels($category)->toArray();

                    $category->set('channelsIds', array_column($channels, 'id'));
                    $category->set('channelsNames', array_column($channels, 'name', 'id'));
                }
            }
        }

        return $result;
    }

    /**
     * Is child category
     *
     * @param string $categoryId
     * @param string $selectedCategoryId
     *
     * @return bool
     */
    public function isChildCategory(string $categoryId, string $selectedCategoryId): bool
    {
        // get category
        if (empty($category = $this->getEntityManager()->getEntity('Category', $selectedCategoryId))) {
            return false;
        }

        return in_array($categoryId, explode("|", (string)$category->get('categoryRoute')));
    }

    /**
     * Get id parent category and ids children category
     *
     * @param string $id
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getIdsTree(string $id): array
    {
        /** @var \Pim\Entities\Category $category */
        $category = $this->getEntityManager()->getEntity('Category', $id);

        $categoriesIds = [];
        $categoriesChild = $category->getChildren()->toArray();

        if (!empty($categoriesChild)) {
            $categoriesChild = $category->getChildren()->toArray();
            $categoriesIds = array_column($categoriesChild, 'id');
        }

        $categoriesIds[] = $category->id;

        return $categoriesIds;
    }

    /**
     * @param Entity $category
     *
     * @return EntityCollection
     */
    protected function getRootChannels(Entity $category): EntityCollection
    {
        $categoryRoute = explode('|', $category->get('categoryRoute'));
        $categoryRootId = (isset($categoryRoute[1])) ? $categoryRoute[1] : $category->get('id');
        if (!isset($this->roots[$categoryRootId])) {
            $this->roots[$categoryRootId] = $this->getEntityManager()->getEntity('Category', $categoryRootId);
        }

        return $this->roots[$categoryRootId]->get('channels');
    }
}
