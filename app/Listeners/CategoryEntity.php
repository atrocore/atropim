<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Treo\Core\EventManager\Event;

/**
 * Class ProductEntity
 *
 * @package Pim\Listeners
 * @author  r.ratsun@gmail.com
 */
class CategoryEntity extends AbstractEntityListener
{
    /**
     * Get category route
     *
     * @param Entity $entity
     * @param bool   $isName
     *
     * @return string
     */
    public static function getCategoryRoute(Entity $entity, bool $isName = false): string
    {
        // prepare result
        $result = '';

        // prepare data
        $data = [];

        while (!empty($parent = $entity->get('categoryParent'))) {
            // push id
            if (!$isName) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim($parent->get('name'));
            }

            // to next category
            $entity = $parent;
        }

        if (!empty($data)) {
            if (!$isName) {
                $result = '|' . implode('|', array_reverse($data)) . '|';
            } else {
                $result = implode(' > ', array_reverse($data));
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        // is code valid
        if (!$this->isCodeValid($entity)) {
            throw new BadRequest($this->translate('Code is invalid', 'exceptions', 'Global'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId') && count($entity->getTreeProducts()) > 0) {
            throw new BadRequest($this->exception('Category has linked products'));
        }

        if ((count($entity->get('catalogs')) > 0 || !empty($entity->get('catalogsIds')))
            && !empty($entity->get('categoryParent'))) {
            throw new BadRequest($this->translate('Only root category can be linked with catalog', 'exceptions', 'Catalog'));
        }

        if (!empty($parent = $entity->get('categoryParent'))
            && !empty($parent->get('products'))
            && !empty(count($parent->get('products')))) {
            throw new BadRequest(
                $this->translate(
                    'Parent category has products',
                    'exceptions',
                    'Category'
                )
            );
        }

        // cascade products relating
        $this->cascadeProductsRelating($entity);
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // build tree
        $this->updateCategoryTree($entity);

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        if (count($event->getArgument('entity')->get('categories')) > 0) {
            throw new BadRequest($this->exception("Category has child category and can't be deleted"));
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'catalogs' && !empty($event->getArgument('entity')->get('categoryParent'))) {
            throw new BadRequest($this->translate('Only root category can be linked with catalog', 'exceptions', 'Catalog'));
        }

        if ($event->getArgument('relationName') == 'products') {
            $product = $event->getArgument('foreign');
            if (is_string($product)) {
                $product = $this->getEntityManager()->getEntity('Product', $product);
            }

            $this->getProductRepository()->isCategoryFromCatalogTrees($product, $event->getArgument('entity'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'products') {
            $productId = !is_string($event->getArgument('foreign')) ? (string)$event->getArgument('foreign')->get('id') : $event->getArgument('foreign');
            $categoryId = (string)$event->getArgument('entity')->get('id');

            $this->getProductRepository()->updateProductCategorySortOrder($productId, $categoryId);
        }
    }

    /**
     * @inheritdoc
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Category');
    }

    /**
     * Update category tree
     *
     * @param Entity $entity
     */
    protected function updateCategoryTree(Entity $entity)
    {
        // is has changes
        if ((empty($entity->categoryListener)
            && ($entity->isAttributeChanged('categoryParentId')
                || $entity->isNew()
                || $entity->isAttributeChanged('name')))) {
            // set route for current category
            $entity->set('categoryRoute', self::getCategoryRoute($entity));
            $entity->set('categoryRouteName', self::getCategoryRoute($entity, true));

            $this->saveEntity($entity);

            // update all children
            if (!$entity->isNew()) {
                $children = $this->getEntityChildren($entity->get('categories'), []);
                foreach ($children as $child) {
                    // set route for child category
                    $child->set('categoryRoute', self::getCategoryRoute($child));
                    $child->set('categoryRouteName', self::getCategoryRoute($child, true));
                    $this->saveEntity($child);
                }
            }
        }
    }

    /**
     * Activate parents categories if it needs
     *
     * @param Entity $entity
     */
    protected function activateParents(Entity $entity)
    {
        // is activate action
        $isActivate = $entity->isAttributeChanged('isActive') && $entity->get('isActive');

        if (empty($entity->categoryListener) && $isActivate && !$entity->isNew()) {
            // update all parents
            foreach ($this->getEntityParents($entity, []) as $parent) {
                $parent->set('isActive', true);
                $this->saveEntity($parent);
            }
        }
    }

    /**
     * Deactivate children categories if it needs
     *
     * @param Entity $entity
     */
    protected function deactivateChildren(Entity $entity)
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->categoryListener) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $child->set('isActive', false);
                $this->saveEntity($child);
            }
        }
    }

    /**
     * Save entity
     *
     * @param Entity $entity
     */
    protected function saveEntity(Entity $entity)
    {
        // set flag
        $entity->categoryListener = true;

        $this
            ->getEntityManager()
            ->saveEntity($entity);
    }

    /**
     * Get entity parents
     *
     * @param Entity $category
     * @param array  $parents
     *
     * @return array
     */
    protected function getEntityParents(Entity $category, array $parents): array
    {
        $parent = $category->get('categoryParent');
        if (!empty($parent)) {
            $parents[] = $parent;
            $parents = $this->getEntityParents($parent, $parents);
        }

        return $parents;
    }

    /**
     * Get all children by recursive
     *
     * @param array $entities
     * @param array $children
     *
     * @return array
     */
    protected function getEntityChildren($entities, array $children)
    {
        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $children[] = $entity;
            }
            foreach ($entities as $entity) {
                $children = $this->getEntityChildren($entity->get('categories'), $children);
            }
        }

        return $children;
    }

    /**
     * @param Entity $entity
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function cascadeProductsRelating(Entity $entity)
    {
        if ($entity->isAttributeChanged('channelsIds')) {
            /** @var \Pim\Repositories\Channel $channelRepository */
            $channelRepository = $this->getEntityManager()->getRepository('Channel');

            /** @var EntityCollection $oldChannels */
            $oldChannels = $entity->get('channels');

            /** @var array $newChannelsIds */
            $newChannelsIds = $entity->get('channelsIds');

            foreach ($oldChannels as $oldChannel) {
                if (!in_array($oldChannel->get('id'), $newChannelsIds)) {
                    // unrelate prev
                    $channelRepository->cascadeProductsRelating($entity->get('id'), $oldChannel, true);
                }
            }

            foreach ($newChannelsIds as $newChannelId) {
                if (!in_array($newChannelId, array_column($oldChannels->toArray(), 'id'))) {
                    // relate new
                    $channelRepository->cascadeProductsRelating($entity->get('id'), $channelRepository->get($newChannelId));
                }
            }
        }
    }

    /**
     * @return \Pim\Repositories\Product
     */
    protected function getProductRepository(): \Pim\Repositories\Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }
}
