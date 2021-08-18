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
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        $this->filterByChannels($params);

        return parent::getSelectParams($params, $withAcl, $checkWherePermission);
    }

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
        if ($catalogId === false){
            return;
        }

        if (!empty($catalogId)) {
            $catalog = $this->getEntityManager()->getEntity('Catalog', $catalogId);
            if (empty($catalog)) {
                throw new BadRequest('No such catalog');
            }
            $treesIds = array_column($catalog->get('categories')->toArray(), 'id');
        } else {
            $treesIds = $this->getEntityManager()->getRepository('Category')->getNotRelatedWithCatalogsTreeIds();
        }

        if (!empty($treesIds)) {
            $where[] = ['id' => $treesIds];
            foreach ($treesIds as $catalogTree) {
                $where[] = ['categoryRoute*' => "%|" . $catalogTree . "|%"];
            }
            $result['whereClause'][] = ['OR' => $where];
        } else {
            $result['whereClause'][] = ['id' => -1];
        }
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
     * @param array $params
     */
    protected function filterByChannels(array &$params)
    {
        if (!empty($params['where'])) {
            foreach ($params['where'] as $k => $row) {
                if ($row['attribute'] == 'channels') {
                    // skip filter if empty value
                    if (in_array($row['type'], ['linkedWith', 'notLinkedWith']) && empty($row['value'])) {
                        unset($params['where'][$k]);
                        $params['where'] = array_values($params['where']);
                        continue 1;
                    }

                    if (!empty($row['value'])) {
                        $channels = $this
                            ->getEntityManager()
                            ->getRepository('Channel')
                            ->where(['id' => $row['value']])
                            ->find();

                    } else {
                        $channels = $this
                            ->getEntityManager()
                            ->getRepository('Channel')
                            ->where(['categoryId!=' => null])
                            ->find();
                    }

                    // prepare categories
                    $categories = [];
                    if ($channels->count() > 0) {
                        foreach ($channels as $channel) {
                            if (!empty($category = $channel->get('category'))) {
                                $categories = array_merge($categories, array_column($category->getChildren()->toArray(), 'id'));
                                $categories[] = $category->get('id');
                            }
                        }
                    }

                    switch ($row['type']) {
                        case 'linkedWith':
                        case 'isLinked':
                            $params['where'][$k] = [
                                'type'      => 'in',
                                'attribute' => 'id',
                                'value'     => $categories
                            ];
                            break;
                        case 'notLinkedWith':
                        case 'isNotLinked':
                            $params['where'][$k] = [
                                'type'      => 'notIn',
                                'attribute' => 'id',
                                'value'     => $categories
                            ];
                            break;
                    }
                }
            }
        }
    }
}
