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

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class Channel
 */
class Channel extends Base
{
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

    /**
     * @param string $categoryRootId
     * @param Entity $channel
     * @param bool   $unrelate
     *
     * @return bool
     * @throws \Espo\Core\Exceptions\Error
     */
    public function cascadeProductsRelating(string $categoryRootId, Entity $channel, bool $unrelate = false): bool
    {
        $categoryRoot = $this->getEntityManager()->getEntity('Category', $categoryRootId);
        if (empty($categoryRoot)) {
            return false;
        }

        /** @var Product $productRepository */
        $productRepository = $this->getEntityManager()->getRepository('Product');

        // find products
        $products = $productRepository
            ->distinct()
            ->select(['id'])
            ->join('categories')
            ->where(['categories.id' => array_column($categoryRoot->getChildren()->toArray(), 'id')])
            ->find();

        foreach ($products as $product) {
            if ($unrelate) {
                $product->skipIsFromCategoryTreeValidation = true;
                $productRepository->unrelateForce($product, 'channels', $channel);
            } else {
                $product->skipValidation('isChannelAlreadyRelated');
                $product->fromCategoryTree = true;
                $productRepository->relate($product, 'channels', $channel);
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('locales'))) {
            $entity->set('locales', ['mainLocale']);
        }

        parent::beforeSave($entity, $options);
    }
}
