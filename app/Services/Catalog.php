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

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

/**
 * Catalog service
 */
class Catalog extends Base
{
    /**
     * @inheritdoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        // get products count
        $productsCount = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id'])
            ->where(['catalogId' => $entity->get('id')])
            ->count();

        // set products count to entity
        $entity->set('productsCount', (int)$productsCount);
    }

    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateProducts(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($products = $duplicatingEntity->get('products'))) {
            // get language
            $language = $this->getInjection('language');

            foreach ($products as $product) {
                if (!in_array($product->get('type'), array_keys($this->getMetadata()->get('pim.productType', [])))) {
                    continue 1;
                }

                // prepare name
                $name = sprintf(
                    $language->translate("createProductForCatalog", "queueManager", "Catalog"),
                    $product->get('name'),
                    $entity->get('name')
                );

                // prepare data
                $data = [
                    'productId' => $product->get('id'),
                    'catalogId' => $entity->get('id')
                ];

                $this
                    ->getInjection('queueManager')
                    ->push($name, 'QueueManagerDuplicateProduct', $data);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
        $this->addDependency('queueManager');
        $this->addDependency('language');
    }
}
