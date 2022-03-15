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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Channel
 */
class Channel extends Base
{
    public function getProductsRelationData(string $id): array
    {
        $data = $this
            ->getEntityManager()
            ->nativeQuery(
                "SELECT product_id as productId, is_active AS isActive FROM product_channel WHERE channel_id='$id' AND deleted=0"
            )
            ->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            $result[$row['productId']] = $row;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getUsedLocales(): array
    {
        $locales = [];
        foreach ($this->select(['locales'])->find()->toArray() as $item) {
            $locales = array_merge($locales, $item['locales']);
        }

        return array_values(array_unique($locales));
    }

    public function relateCategories(Entity $entity, $foreign, $data, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->relateChannels($category, $entity, null, $options);
    }

    public function unrelateCategories(Entity $entity, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $category = $foreign;
        if (is_string($foreign)) {
            $category = $this->getEntityManager()->getRepository('Category')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Category')->unrelateChannels($category, $entity, $options);
    }

    public function unrelateProducts(Entity $entity, $foreign, $options)
    {
        if (is_bool($foreign)) {
            throw new BadRequest($this->getInjection('language')->translate('massUnRelateBlocked', 'exceptions'));
        }

        $product = $foreign;
        if (is_string($foreign)) {
            $product = $this->getEntityManager()->getRepository('Product')->get($foreign);
        }

        return $this->getEntityManager()->getRepository('Product')->unrelateChannels($product, $entity, $options);
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        if (!empty($products = $entity->get('products')) && count($products) > 0) {
            foreach ($products as $product) {
                $this->unrelateProducts($entity, $product, []);
            }
        }

        if (!empty($categories = $entity->get('categories')) && count($categories) > 0) {
            foreach ($categories as $category) {
                $this->unrelateCategories($entity, $category, []);
            }
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('locales'))) {
            $entity->set('locales', ['main']);
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'products') {
            $this->getEntityManager()->getRepository('Product')->relatePfas($foreign, $entity);
        }

        parent::afterRelate($entity, $relationName, $foreign, $data, $options);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('queueManager');
        $this->addDependency('language');
    }
}
