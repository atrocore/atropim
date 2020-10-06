<?php

declare(strict_types=1);

namespace Pim\SelectManagers;

use Espo\Core\Exceptions\BadRequest;
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
     * @throws BadRequest
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function boolFilterOnlyCatalogCategories(array &$result)
    {
        /** @var \Pim\Entities\Catalog $catalog */
        $catalog = $this->getEntityManager()->getEntity('Catalog', (string)$this->getSelectCondition('onlyCatalogCategories'));
        if (empty($catalog)) {
            throw new BadRequest('No such catalog');
        }

        /** @var array $treesIds */
        $treesIds = array_column($catalog->get('categories')->toArray(), 'id');

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

        try {
            $root = $category->getRoot();
        } catch (\Throwable $e) {
            // skip exceptions
        }

        if (!empty($root)) {
            $result['whereClause'][] = [
                'categoryRoute*' => "%|{$root->id}|%"
            ];
        }
    }
}
