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

namespace Pim\Migrations;

use Espo\Core\Exceptions\Error;
use Treo\Core\Migration\Base;

class V1Dot4Dot7 extends Base
{
    public function up(): void
    {
        $this->exec("DELETE FROM `catalog_category` WHERE deleted=0");
        foreach ($this->getPDO()->query("SELECT * FROM `catalog_category` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            $categoriesIds = $this
                ->getPDO()
                ->query("SELECT id FROM `category` WHERE deleted=0 AND category_route LIKE '%|{$record['category_id']}|%'")
                ->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($categoriesIds as $categoryId) {
                $this->exec("INSERT INTO `catalog_category` (`category_id`,`catalog_id`) VALUES ('{$categoryId}', '{$record['catalog_id']}')");
            }
        }

        $this->exec(
            "CREATE TABLE `category_channel` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `category_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `channel_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_6521CE3912469DE2` (category_id), INDEX `IDX_6521CE3972F5A1AA` (channel_id), UNIQUE INDEX `UNIQ_6521CE3912469DE272F5A1AA` (category_id, channel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );
        $channels = $this->getPDO()->query("SELECT * FROM channel WHERE deleted=0 AND category_id IS NOT NULL")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($channels as $channel) {
            $this->exec("INSERT INTO `category_channel` (`category_id`,`channel_id`) VALUES ('{$channel['category_id']}', '{$channel['id']}')");

            $categoriesIds = $this
                ->getPDO()
                ->query("SELECT id FROM `category` WHERE deleted=0 AND category_route LIKE '%|{$channel['category_id']}|%'")
                ->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($categoriesIds as $categoryId) {
                $this->exec("INSERT INTO `category_channel` (`category_id`,`channel_id`) VALUES ('{$categoryId}', '{$channel['id']}')");
            }
        }
        $this->exec("DROP INDEX IDX_CATEGORY_ID ON `channel`");
        $this->exec("ALTER TABLE `channel` DROP category_id");
        $this->exec("DROP INDEX id ON `category_channel`");
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
