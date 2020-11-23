<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Class V1Dot0Dot56
 *
 * @package Pim\Migrations
 */
class V1Dot0Dot56 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $contentSql = "
            CREATE TABLE `content` (
                `id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci,
                `name` VARCHAR(128) DEFAULT '' COLLATE utf8mb4_unicode_ci,
                `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci,
                `tags` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci COMMENT 'default={[]}',
                `status` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `type` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `description` TEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `text` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `meta_title` VARCHAR(60) DEFAULT '' COLLATE utf8mb4_unicode_ci,
                `meta_description` TINYTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `content_group_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `owner_user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `assigned_user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                INDEX `IDX_CONTENT_GROUP_ID` (content_group_id),
                INDEX `IDX_CREATED_BY_ID` (created_by_id),
                INDEX `IDX_MODIFIED_BY_ID` (modified_by_id),
                INDEX `IDX_OWNER_USER_ID` (owner_user_id),
                INDEX `IDX_ASSIGNED_USER_ID` (assigned_user_id),
                INDEX `IDX_OWNER_USER` (owner_user_id, deleted),
                INDEX `IDX_ASSIGNED_USER` (assigned_user_id, deleted),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ";

        $contentGroupSql = "
            CREATE TABLE `content_group` (
                `id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci,
                `name` VARCHAR(60) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci,
                `created_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `modified_at` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `created_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `modified_by_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `owner_user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                `assigned_user_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci,
                INDEX `IDX_CREATED_BY_ID` (created_by_id),
                INDEX `IDX_MODIFIED_BY_ID` (modified_by_id),
                INDEX `IDX_OWNER_USER_ID` (owner_user_id),
                INDEX `IDX_ASSIGNED_USER_ID` (assigned_user_id),
                INDEX `IDX_OWNER_USER` (owner_user_id, deleted),
                INDEX `IDX_ASSIGNED_USER` (assigned_user_id, deleted),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
        ";

        // create Content and Content Group entities
        $this->execute($contentSql);
        $this->execute($contentGroupSql);
        $this->execute("ALTER TABLE `product_contents` ADD content_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD product_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci;");
        $this->execute("CREATE INDEX IDX_7F4A7BE084A0A3ED ON `product_contents` (content_id);");
        $this->execute("CREATE INDEX IDX_7F4A7BE04584665A ON `product_contents` (product_id);");
        $this->execute("CREATE UNIQUE INDEX UNIQ_7F4A7BE084A0A3ED4584665A ON `product_contents` (content_id, product_id)");

        // create `code` field for Association
        $this->execute("ALTER TABLE `association` ADD code VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci;");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        // remove `code` field for Association
        $this->execute("ALTER TABLE `association` DROP code;");

        // drop indexes
        $this->execute("DROP INDEX IDX_7F4A7BE04584665A ON `product_contents`;");
        $this->execute("DROP INDEX IDX_7F4A7BE084A0A3ED ON `product_contents`;");
        $this->execute("DROP INDEX UNIQ_7F4A7BE084A0A3ED4584665A ON `product_contents`;");
        $this->execute("ALTER TABLE `product_contents` DROP content_id, DROP product_id;");
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}