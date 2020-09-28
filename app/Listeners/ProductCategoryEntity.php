<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;
use Treo\Listeners\AbstractListener;

/**
 * Class ProductCategoryEntity
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class ProductCategoryEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        if (empty($product = $entity->get('product')) || empty($category = $entity->get('category'))) {
            throw new BadRequest($this->exception('Product and Category cannot be empty'));
        }

        if (!$this->isUnique($entity)) {
            throw new BadRequest($this->exception('Such record already exists'));
        }

        if (empty($catalog = $product->get('catalog'))) {
            throw new BadRequest($this->exception('No such product catalog'));
        }

        if (!$this->isCategoryInCatalog($category, $catalog)) {
            throw new BadRequest($this->exception('Category should be in catalog trees'));
        }

        // clearing channels ids
        if ($entity->get('scope') == 'Global') {
            $entity->set('channelsIds', []);
        }
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        $category = $this
            ->getEntityManager()
            ->getRepository('ProductCategory')
            ->select(['id'])
            ->where(
                [
                    'id!='       => $entity->get('id'),
                    'productId'  => $entity->get('productId'),
                    'categoryId' => $entity->get('categoryId'),
                    'scope'      => $entity->get('scope'),
                ]
            )
            ->findOne();

        return empty($category);
    }

    /**
     * @param Entity $category
     * @param Entity $catalog
     *
     * @return bool
     */
    protected function isCategoryInCatalog(Entity $category, Entity $catalog): bool
    {
        $categoryTree = array_merge([$category->get('id')], explode("|", (string)$category->get('categoryRoute')));
        foreach ($catalog->get('categories') as $tree) {
            if (in_array($tree->get('id'), $categoryTree)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getContainer()->get('language')->translate($key, 'exceptions', 'ProductCategory');
    }
}
