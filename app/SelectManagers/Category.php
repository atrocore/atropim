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

declare(strict_types=1);

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of Category
 */
class Category extends AbstractSelectManager
{
    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        // prepare additional select columns
        $additionalSelectColumns = [
            'childrenCount' => '(SELECT COUNT(c1.id) FROM category AS c1 WHERE c1.category_parent_id=category.id AND c1.deleted=0)'
        ];

        // add additional select columns
        foreach ($additionalSelectColumns as $alias => $sql) {
            $result['additionalSelectColumns'][$sql] = $alias;
        }
    }

    protected function boolFilterNotParents(&$result): void
    {
        $notParents = (string)$this->getSelectCondition('notParents');
        if (empty($notParents)) {
            return;
        }

        $category = $this->getEntityManager()->getRepository('Category')->get($notParents);
        if (!empty($category)) {
            $result['whereClause'][] = [
                'id!=' => array_merge($category->getParentsIds(), [$category->get('id')])
            ];
        }
    }

    protected function boolFilterNotChildren(&$result): void
    {
        $notChildren = (string)$this->getSelectCondition('notChildren');
        if (empty($notChildren)) {
            return;
        }

        $category = $this->getEntityManager()->getRepository('Category')->get($notChildren);
        if (!empty($category)) {
            $result['whereClause'][] = [
                'id!=' => array_merge(array_column($category->getChildren()->toArray(), 'id'), [$category->get('id')])
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyRootCategory(array &$result)
    {
        if ($this->hasBoolFilter('onlyRootCategory')) {
            $result['whereClause'][] = [
                'categoryParentId' => null
            ];
        }
    }

    /**
     * @param array $result
     *
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterOnlyCatalogCategories(array &$result)
    {
        $catalogId = $this->getSelectCondition('onlyCatalogCategories');

        if (empty($catalogId)) {
            $result['whereClause'][] = [
                'id!=' => $this
                    ->getEntityManager()
                    ->getPDO()
                    ->query("SELECT category_id FROM `catalog_category` WHERE deleted=0")
                    ->fetchAll(\PDO::FETCH_COLUMN)
            ];
            return;
        }

        $result['whereClause'][] = [
            'id' => $this
                ->getEntityManager()
                ->getPDO()
                ->query("SELECT category_id FROM `catalog_category` WHERE deleted=0 AND catalog_id=" . $this->getEntityManager()->getPDO()->quote($catalogId))
                ->fetchAll(\PDO::FETCH_COLUMN)
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyLeafCategories(array &$result)
    {
        if (!$this->getConfig()->get('productCanLinkedWithNonLeafCategories', false)) {
            $parents = $this
                ->getEntityManager()
                ->getRepository('Category')->select(['categoryParentId'])->where(['categoryParentId!=' => null])
                ->find()
                ->toArray();

            if (!empty($parents)) {
                $result['whereClause'][] = [
                    'id!=' => array_unique(array_column($parents, 'categoryParentId'))
                ];
            }
        }
    }

    /**
     * @param $result
     *
     * @return void
     */
    protected function boolFilterLinkedWithProduct(&$result)
    {
        if ($this->hasBoolFilter('linkedWithProduct')) {
            $list = $this
                ->getEntityManager()
                ->getRepository('Category')
                ->select(['id', 'categoryRoute'])
                ->join('products')
                ->find()
                ->toArray();

            if ($list) {
                $ids = [];

                foreach ($list as $category) {
                    $ids[] = $category['id'];

                    $parentCategoriesIds = explode("|", trim($category['categoryRoute'], "|"));
                    $ids = array_merge($ids, $parentCategoriesIds);
                }

                $result['whereClause']['id'] = array_unique($ids);
            } else {
                $result['whereClause']['id'] = null;
            }
        }
    }
}
