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
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // is sku valid
        if (!$this->isSkuUnique($entity)) {
            throw new BadRequest($this->exception('Product with such SKU already exist'));
        }

        if ($entity->isAttributeChanged('catalogId')) {
            $this
                ->getEntityManager()
                ->getRepository('Product')
                ->isProductCategoriesInSelectedCatalog($entity, $entity->get('catalog'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->exception('You can\'t change field of Type'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        /** @var Entity $entity */
        $entity = $event->getArgument('entity');

        /** @var array $options */
        $options = $event->getArgument('options');

        if (
            empty($entity->skipUpdateProductAttributesByProductFamily)
            && empty($options['skipProductFamilyHook'])
            && empty($entity->isDuplicate)
            && $entity->isAttributeChanged('productFamilyId')
        ) {
            $this->updateProductAttributesByProductFamily($entity, $options);
        }
    }

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
            $category = $event->getArgument('foreign');
            if (is_string($category)) {
                $category = $this->getEntityManager()->getEntity('Category', $category);
            }

            $this->getProductRepository()->isCategoryAlreadyRelated($product, $category);
            $this->getProductRepository()->isCategoryFromCatalogTrees($product, $category);
            $this->getProductRepository()->isProductCanLinkToNonLeafCategory($category);
        }

        if ($event->getArgument('relationName') == 'channels') {
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
                throw new BadRequest($this->exception("Channel provided by category tree can't be unlinked from product"));
            }
        }
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        //set default value in isActive for channel after deleted link
        if ($event->getArgument('relationName') == 'channels' && $event->getArgument('foreign') instanceof Channel) {
            $dataEntity = new \StdClass();
            $dataEntity->entityName = 'Product';
            $dataEntity->entityId = $event->getArgument('entity')->get('id');
            $dataEntity->value = (int)!empty(
            $event
                ->getArgument('entity')
                ->getRelations()['channels']['additionalColumns']['isActive']['default']
            );

            $this
                ->getService('Channel')
                ->setIsActiveEntity($event->getArgument('foreign')->get('id'), $dataEntity, true);
        }

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
     * @param Entity $product
     * @param string $field
     *
     * @return bool
     */
    protected function isSkuUnique(Entity $product): bool
    {
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['sku' => $product->get('sku'), 'catalogId' => $product->get('catalogId')])
            ->find();

        if (count($products) > 0) {
            foreach ($products as $item) {
                if ($item->get('id') != $product->get('id')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return bool
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function updateProductAttributesByProductFamily(Entity $entity, array $options): bool
    {
        if (!$entity->isNew() && $entity->isAttributeChanged('productFamilyId')) {
            // unlink attributes from old product family
            $this
                ->getEntityManager()
                ->nativeQuery(
                    "UPDATE product_attribute_value SET product_family_attribute_id=NULL WHERE product_id=:productId AND product_family_attribute_id IS NOT NULL AND deleted=0",
                    ['productId' => $entity->get('id')]
                );
        }

        if (empty($productFamily = $entity->get('productFamily'))) {
            return true;
        }

        // get product family attributes
        $productFamilyAttributes = $productFamily->get('productFamilyAttributes');

        if (count($productFamilyAttributes) > 0) {
            /** @var \Pim\Repositories\ProductAttributeValue $repository */
            $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');

            foreach ($productFamilyAttributes as $productFamilyAttribute) {
                // create
                $productAttributeValue = $repository->get();
                $productAttributeValue->set(
                    [
                        'productId'                => $entity->get('id'),
                        'attributeId'              => $productFamilyAttribute->get('attributeId'),
                        'productFamilyAttributeId' => $productFamilyAttribute->get('id'),
                        'isRequired'               => $productFamilyAttribute->get('isRequired'),
                        'scope'                    => $productFamilyAttribute->get('scope'),
                        'channelId'                => $productFamilyAttribute->get('channelId')
                    ]
                );

                // skip validation by channel
                $productAttributeValue->skipProductChannelValidation = true;

                // save
                try {
                    $this->getEntityManager()->saveEntity($productAttributeValue);
                } catch (ProductAttributeAlreadyExists $e) {
                    $copy = $repository->findCopy($productAttributeValue);
                    $copy->set('productFamilyAttributeId', $productFamilyAttribute->get('id'));
                    $copy->set('isRequired', $productAttributeValue->get('isRequired'));

                    $copy->skipPfValidation = true;
                    $copy->skipProductChannelValidation = true;

                    $this->getEntityManager()->saveEntity($copy);
                }
            }
        }

        return true;
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
