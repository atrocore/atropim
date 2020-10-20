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

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Class V1Dot0Dot20
 */
class V1Dot0Dot20 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->execute("DROP TABLE product_family_attribute_channel");
        $this->execute("ALTER TABLE `product_family_attribute` ADD channel_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_CHANNEL_ID ON `product_family_attribute` (channel_id)");
        $this->execute("DROP TABLE product_attribute_value_channel");
        $this->execute("ALTER TABLE `product_attribute_value` ADD channel_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->execute("CREATE INDEX IDX_CHANNEL_ID ON `product_attribute_value` (channel_id)");
        $this->execute("UPDATE product_attribute_value SET deleted=1 WHERE deleted=0 AND scope='Channel'");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->execute("CREATE TABLE `product_family_attribute_channel` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `channel_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `product_family_attribute_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_564D0D5672F5A1AA` (channel_id), INDEX `IDX_564D0D5658DA10F5` (product_family_attribute_id), UNIQUE INDEX `UNIQ_564D0D5672F5A1AA58DA10F5` (channel_id, product_family_attribute_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("CREATE TABLE `product_attribute_value_channel` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `channel_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `product_attribute_value_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_A5FC213872F5A1AA` (channel_id), INDEX `IDX_A5FC21389774A42E` (product_attribute_value_id), UNIQUE INDEX `UNIQ_A5FC213872F5A1AA9774A42E` (channel_id, product_attribute_value_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->execute("DROP INDEX IDX_CHANNEL_ID ON `product_attribute_value`");
        $this->execute("ALTER TABLE `product_attribute_value` DROP channel_id");
        $this->execute("DROP INDEX IDX_CHANNEL_ID ON `product_family_attribute`");
        $this->execute("ALTER TABLE `product_family_attribute` DROP channel_id");
        $this->execute("UPDATE product_attribute_value SET deleted=1 WHERE deleted=0 AND scope='Channel'");
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
