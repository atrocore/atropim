<?php

declare(strict_types=1);

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of Category
 *
 * @author r.ratsun <r.ratsun@gmail.com>
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
     * @return mixed
     */
    protected function boolFilterOnlyCatalogCategories(array &$result)
    {
        // get id
        $value = $this->getSelectCondition('onlyCatalogCategories');

        // get catalog
        if (empty($value)) {
            return null;
        }

        // get catalog trees
        $catalogs = $this
            ->getEntityManager()
            ->getRepository('Catalog')
            ->where(['id' => $value])
            ->find();

        $catalogsTrees = [];

        if (count($catalogs) > 0) {
            foreach ($catalogs as $catalog) {
                $catalogsTrees = array_merge($catalogsTrees, array_column($catalog->get('categories')->toArray(), 'id'));
            }
        }

        if (!empty($catalogsTrees)) {
            // prepare where
            $where[] = ['id' => $catalogsTrees];
            foreach ($catalogsTrees as $catalogTree) {
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
    protected function boolFilterNotChildCategory(array &$result)
    {
        // prepare category id
        $categoryId = (string)$this->getSelectCondition('notChildCategory');

        $result['whereClause'][] = [
            'categoryRoute!*' => "%|$categoryId|%"
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterFromCategoryTree(array &$result)
    {
        // get category
        $category = $this
            ->getEntityManager()
            ->getEntity('Category', $this->getSelectCondition('fromCategoryTree'));

        /** @var string $rootId */
        $rootId = $category->getRoot()->get('id');

        $result['whereClause'][] = [
            'categoryRoute*' => "%|$rootId|%"
        ];
    }
}
