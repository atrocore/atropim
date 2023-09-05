<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\ORM\Entity;

/**
 * Service of Channel
 */
class Channel extends Base
{
    /**
     * Get product data for channel
     *
     * @param string $channelId
     *
     * @return array
     */
    public function getProducts(string $channelId): array
    {
        // prepare result
        $result = [];

        if (!empty($products = $this->getChannelCategoryProducts($channelId))) {
            foreach ($products as $product) {
                // prepare categories
                $categories = [];
                if (isset($result[$product['productId']]['categories'])) {
                    $categories = $result[$product['productId']]['categories'];
                }
                if (!empty($product['categoryName'])) {
                    $categories[] = $product['categoryName'];
                }

                $result[$product['productId']] = [
                    'productId'   => (string)$product['productId'],
                    'productName' => (string)$product['productName'],
                    'isActive'    => (bool)$product['isActive'],
                    'categories'  => $categories
                ];
            }

            // prepare result
            $result = array_values($result);
        }

        return $result;
    }

    /**
     * Init
     */
    protected function init()
    {
        parent::init();

        // add dependencies
        $this->addDependency('serviceFactory');
    }

    /**
     * Get channel category products data
     *
     * @param string $channelId
     *
     * @return array
     */
    protected function getChannelCategoryProducts(string $channelId): array
    {
        // prepare result
        $products = [];

        if (!empty($category = $this->getDBChannelCategory($channelId))) {
            // get categories
            $categories = $this->getCategoryChildren($category);
            $categories[] = $category;

            // get products
            $products = $this->getDBCategoriesProducts($categories);
        }

        return $products;
    }

    /**
     * Get channel category from DB
     *
     * @param string $channelId
     *
     * @return string
     */
    protected function getDBChannelCategory(string $channelId)
    {
        $sql
            = "SELECT
                  ct.category_id 
                FROM channel AS c
                JOIN catalog AS ct ON ct.id = c.catalog_id AND ct.deleted = 0 AND ct.is_active = 1
                WHERE c.deleted = 0 AND c.id = '{$channelId}'";
        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute();

        $data = $sth->fetch(\PDO::FETCH_ASSOC);

        return (!empty($data['category_id'])) ? $data['category_id'] : null;
    }

    public function getAclWhereSql(string $entityName, string $entityAlias): string
    {
        // prepare sql
        $sql = '';

        if (!$this->getUser()->isAdmin()) {
            // prepare data
            $userId = $this->getUser()->get('id');

            if ($this->getAcl()->checkReadOnlyOwn($entityName)) {
                $sql .= " AND $entityAlias.assigned_user_id = '$userId'";
            }
            if ($this->getAcl()->checkReadOnlyTeam($entityName)) {
                $sql .= " AND $entityAlias.id IN ("
                    . "SELECT et.entity_id "
                    . "FROM entity_team AS et "
                    . "JOIN team_user AS tu ON tu.team_id=et.team_id "
                    . "WHERE et.deleted=0 AND tu.deleted=0 "
                    . "AND tu.user_id = '$userId' AND et.entity_type='$entityName')";
            }
        }

        return $sql;
    }

    /**
     * Get channel categories from DB
     *
     * @param array $categories
     *
     * @return array
     */
    protected function getDBCategoriesProducts(array $categories): array
    {
        // prepare data
        $where = $this->getAclWhereSql('Category', 'c');
        $pdo = $this->getEntityManager()->getPDO();

        $sql
            = "SELECT
                  c.id        AS categoryId,
                  c.name      AS categoryName,
                  p.id        AS productId,
                  p.name      AS productName,
                  p.is_active AS isActive
                FROM product_category_linker AS l
                 JOIN category AS c ON c.id = l.category_id
                 JOIN product AS p ON p.id = l.product_id AND p.deleted = 0
                WHERE l.deleted = 0 $where AND l.category_id IN (\"" . implode('","', $categories) . "\")";
        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param string $id
     *
     * @return array
     */
    protected function getCategoryChildren(string $id): array
    {
        if (empty($category = $this->getEntityManager()->getEntity('Category', $id))) {
            return [];
        }

        return array_column($category->getChildren()->toArray(), 'id');
    }
}
