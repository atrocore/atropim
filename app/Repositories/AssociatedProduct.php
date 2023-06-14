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

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;

class AssociatedProduct extends Relationship
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('mainProductId') == $entity->get('relatedProductId')) {
            throw new BadRequest($this->getInjection('language')->translate('itselfAssociation', 'exceptions', 'Product'));
        }

        if ($entity->isNew() && $entity->get('sorting') === null) {
            $last = $this->where(['mainProductId' => $entity->get('mainProductId')])->order('sorting', 'DESC')->findOne();
            $entity->set('sorting', empty($last) ? 0 : (int)$last->get('sorting') + 10);
        }
    }

    public function removeByProductId(string $productId): void
    {
        $this->where(['mainProductId' => $productId])->removeCollection();
        $this->where(['relatedProductId' => $productId])->removeCollection();
    }

    public function updateSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (count($collection) === 0) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        /**
         * Delete backward association
         */
        if (empty($options['skipDeleteBackwardAssociatedProduct']) && !empty($backwardAssociatedProduct = $entity->get('backwardAssociatedProduct'))) {
            $this->remove($backwardAssociatedProduct, ['skipDeleteBackwardAssociatedProduct' => true]);
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    public function getAssociationsGroupsData(string $productId, string $language): array
    {
        // prepare suffix
        $languageSuffix = '';
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            if (in_array($language, $this->getConfig()->get('inputLanguageList', []))) {
                $languageSuffix = '_' . strtolower($language);
            }
        }

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('a.id, a.name' . $languageSuffix . ' as association_name')
            ->from('associated_product', 'ap')
            ->leftJoin('ap', 'association', 'a', 'ap.association_id=a.id AND a.deleted=0')
            ->where('ap.deleted=0')
            ->andWhere('ap.main_product_id=:productId')->setParameter('productId', $productId)
            ->groupBy('a.id, association_name');

        $groups = $qb->fetchAllAssociative();

        return $groups;
    }

}
