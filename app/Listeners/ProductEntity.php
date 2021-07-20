<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Pim\Entities\Product;
use Treo\Core\EventManager\Event;
use Pim\Entities\Channel;

/**
 * Class ProductEntity
 */
class ProductEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        /** @var Entity $product */
        $product = $event->getArgument('entity');

        if ($event->getArgument('relationName') == 'categories') {
            $id = $event->getArgument('foreign');
            if (!is_string($id)) {
                $id = $id->get('id');
            }

            $category = $this->getEntityManager()->getEntity('Category', $id);

            $this->getProductRepository()->isCategoryAlreadyRelated($product, $category);
            $this->getProductRepository()->isCategoryFromCatalogTrees($product, $category);
            $this->getProductRepository()->isProductCanLinkToNonLeafCategory($category);
        }

        if ($event->getArgument('relationName') == 'channels' && !$product->isSkippedValidation('isChannelAlreadyRelated')) {
            $this->getProductRepository()->isChannelAlreadyRelated($product, $event->getArgument('foreign'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterRelate(Event $event)
    {
        /** @var Entity $product */
        $product = $event->getArgument('entity');

        if ($event->getArgument('relationName') == 'categories') {
            $this->getProductRepository()->updateProductCategorySortOrder($product, $event->getArgument('foreign'));
            $this->getProductRepository()->linkCategoryChannels($product, $event->getArgument('foreign'));
        }

        if ($event->getArgument('relationName') == 'channels') {
            // set from_category_tree param
            if (!empty($product->fromCategoryTree)) {
                $this->getProductRepository()->updateChannelRelationData($product, $event->getArgument('foreign'), null, true);
            }
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeUnrelate(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        if ($event->getArgument('relationName') == 'channels' && empty($entity->skipIsFromCategoryTreeValidation)) {
            $productId = (string)$entity->get('id');
            $channelId = (string)$event->getArgument('foreign')->get('id');

            $channelRelationData = $this
                ->getEntityManager()
                ->getRepository('Product')
                ->getChannelRelationData($productId);

            if (!empty($channelRelationData[$channelId]['isFromCategoryTree'])) {
                throw new BadRequest($this->exception("channelProvidedByCategoryTreeCantBeUnlinkedFromProduct"));
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'categories') {
            $this->getProductRepository()->linkCategoryChannels($event->getArgument('entity'), $event->getArgument('foreign'), true);
        }
    }

    /**
     * Before action delete
     *
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        $id = $event->getArgument('entity')->id;
        $this->removeProductAttributeValue($id);
    }

    /**
     * @param string $id
     */
    protected function removeProductAttributeValue(string $id)
    {
        $productAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $id])
            ->find();

        foreach ($productAttributes as $attr) {
            $this->getEntityManager()->removeEntity($attr, ['skipProductAttributeValueHook' => true]);
        }
    }

    /**
     * @return \Pim\Repositories\Product
     */
    protected function getProductRepository(): \Pim\Repositories\Product
    {
        return $this->getEntityManager()->getRepository('Product');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Product');
    }
}
