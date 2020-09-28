<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Utils\Util;
use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 3.0.1
 *
 * @author r.ratsun@gmail.com
 */
class V3Dot0Dot1 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        $this->dropTriggers();
        $this->catalogCategoryUp();
        $this->productCategoryUp();
        $this->masterCatalogUp();
        $this->channelsUp();
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
    }

    /**
     * Migrate catalog categories up
     */
    protected function catalogCategoryUp(): void
    {
        $this->execute("DELETE FROM catalog_category WHERE 1");

        $categories = $this->fetchAll("SELECT id FROM category WHERE category_parent_id IS NULL");
        $catalogs = $this->fetchAll("SELECT id FROM catalog");

        if (!empty($categories) && !empty($catalogs)) {
            $sql = "";
            foreach ($categories as $category) {
                foreach ($catalogs as $catalog) {
                    $sql .= "INSERT INTO catalog_category (catalog_id, category_id) VALUES ('" . $catalog['id'] . "', '" . $category['id'] . "');";
                }
            }
            $this->execute($sql);
        }
    }

    /**
     * Migrate product categories up
     */
    protected function productCategoryUp(): void
    {
        $sql
            = "SELECT pcl.* 
                FROM product_category_linker AS pcl 
                JOIN product AS p ON p.id=pcl.product_id 
                JOIN category AS c ON c.id=pcl.category_id 
                WHERE pcl.deleted=0 AND p.deleted=0 AND c.deleted=0";

        try {
            $data = $this->fetchAll($sql);
        } catch (\PDOException $e) {
            $data = [];
        }

        if (!empty($data)) {
            // clear
            $this->execute('DELETE FROM product_category WHERE 1');

            // prepare count
            $count = 0;

            // prepare sql
            $sql = '';

            foreach ($data as $row) {
                // prepare data
                $id = Util::generateId();
                $productId = $row['product_id'];
                $categoryId = $row['category_id'];
                $createdAt = date('Y-m-d H:i:s');

                // prepare sql
                $sql .= "INSERT INTO product_category (id,product_id,category_id,scope,created_by_id,created_at) VALUES ('$id','$productId','$categoryId','Global','system','$createdAt');";

                // prepare count
                $count++;

                if ($count == 1000) {
                    $this->execute($sql);
                    $sql = '';
                    $count = 0;
                }
            }

            if (!empty($sql)) {
                $this->execute($sql);
            }
        }
    }

    /**
     * Migrate master catalog up
     */
    protected function masterCatalogUp(): void
    {
        // clear
        $this->execute("DELETE FROM catalog WHERE code='main_catalog_migration'");

        // prepare data
        $id = Util::generateId();
        $createdAt = date('Y-m-d H:i:s');

        // create
        $this->execute(
            "INSERT INTO catalog (id,name,code,description,is_active,created_by_id,created_at) VALUES ('$id','Main catalog','main_catalog_migration','Auto generated catalog by migration.',1,'system','$createdAt')"
        );

        // update all products
        $this->execute("UPDATE product SET catalog_id='$id' WHERE 1");

        // get root categories
        $categories = $this->fetchAll("SELECT id FROM category WHERE category_parent_id IS NULL AND deleted=0");

        if (!empty($categories)) {
            // clear
            $this->execute("DELETE FROM catalog_category WHERE 1");

            // prepare sql
            $sql = '';

            foreach ($categories as $category) {
                // prepare category id
                $categoryId = $category['id'];

                // prepare sql
                $sql .= "INSERT INTO catalog_category (catalog_id,category_id) VALUES ('$id','$categoryId');";
            }
            if (!empty($sql)) {
                $this->execute($sql);
            }
        }
    }

    /**
     * Migrate channels up
     */
    protected function channelsUp()
    {
        // clear
        $this->execute("DELETE FROM product_channel WHERE 1");

        // get data
        $data = $this
            ->fetchAll(
                "SELECT DISTINCT channel.id   AS channelId, product.id   AS productId
                 FROM catalog
                 JOIN channel ON channel.catalog_id=catalog.id AND channel.deleted=0
                 JOIN category ON (category.category_route LIKE concat('%|',catalog.category_id,'|%') OR category.id=catalog.category_id) AND category.deleted=0
                 JOIN product_category_linker AS pcl ON pcl.category_id=category.id AND pcl.deleted=0
                 JOIN product ON product.id=pcl.product_id AND product.deleted=0
                 WHERE catalog.deleted=0"
            );

        // prepare sql
        $sql = '';

        // prepare count
        $count = 0;

        foreach ($data as $row) {
            // prepare data
            $productId = $row['productId'];
            $channelId = $row['channelId'];

            // prepare sql
            $sql .= "INSERT INTO product_channel (product_id, channel_id) VALUES ('$productId', '$channelId');";

            // prepare count
            $count++;

            if ($count == 1000) {
                $this->execute($sql);

                // prepare sql
                $sql = '';

                // prepare count
                $count = 0;
            }
        }

        if (!empty($sql)) {
            $this->execute($sql);
        }

        try {
            $this->execute('DROP TABLE product_category_linker');
        } catch (\PDOException $e) {
        }
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    protected function execute(string $sql)
    {
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare($sql);
        $sth->execute();

        return $sth;
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    protected function fetchAll(string $sql)
    {
        return $this
            ->execute($sql)
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Drop triggers
     */
    protected function dropTriggers()
    {
        $sql = "DROP TRIGGER IF EXISTS trigger_after_insert_product_family_attribute_linker;";
        $sql .= "DROP TRIGGER IF EXISTS trigger_after_update_product_family_attribute_linker;";
        $sql .= "DROP TRIGGER IF EXISTS trigger_after_insert_product;";
        $sql .= "DROP TRIGGER IF EXISTS trigger_after_update_product;";

        $this->execute($sql);
    }
}
