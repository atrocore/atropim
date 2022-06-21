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

use Treo\Core\Migration\Base;

class V1Dot5Dot33 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE `associated_product` DROP both_directions");
        $this->exec("ALTER TABLE `associated_product` DROP `name`");

        $this->exec("DELETE FROM `associated_product` WHERE deleted=1");

        $unique = [];
        foreach ($this->getPDO()->query("SELECT * FROM `associated_product` WHERE deleted=0")->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            if (empty($record['association_id']) || empty($record['main_product_id']) || empty($record['related_product_id'])) {
                $this->exec("DELETE FROM `associated_product` WHERE id='{$record['id']}'");
                continue 1;
            }

            $key = "{$record['association_id']}_{$record['main_product_id']}_{$record['related_product_id']}";
            if (in_array($key, $unique)) {
                $this->exec("DELETE FROM `associated_product` WHERE id='{$record['id']}'");
                continue 1;
            }

            $unique[] = $key;
        }

        $this->getPDO()->exec(
            "CREATE UNIQUE INDEX UNIQ_C803FBE9EFB9C8A57D7C1239CF496EEAEB3B4E33 ON `associated_product` (association_id, main_product_id, related_product_id, deleted)"
        );

        $this->exec("DROP INDEX IDX_BACKWARD_ASSOCIATION_ID ON `associated_product`");
        $this->exec("ALTER TABLE `associated_product` DROP backward_association_id");
        $this->exec("ALTER TABLE `associated_product` ADD backward_associated_product_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("CREATE INDEX IDX_BACKWARD_ASSOCIATED_PRODUC ON `associated_product` (backward_associated_product_id)");
        $this->exec("DROP INDEX IDX_ASSIGNED_USER_ID ON `associated_product`");
        $this->exec("DROP INDEX IDX_OWNER_USER_ID ON `associated_product`");
        $this->exec("ALTER TABLE `associated_product` DROP owner_user_id, DROP assigned_user_id");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE `associated_product` ADD `name` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `associated_product` ADD both_directions TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("DROP INDEX UNIQ_C803FBE9EFB9C8A57D7C1239CF496EEAEB3B4E33 ON `associated_product`");
        $this->exec("DROP INDEX IDX_BACKWARD_ASSOCIATED_PRODUC ON `associated_product`");
        $this->exec("ALTER TABLE `associated_product` DROP backward_associated_product_id");
        $this->exec("ALTER TABLE `associated_product` ADD backward_association_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("CREATE INDEX IDX_BACKWARD_ASSOCIATION_ID ON `associated_product` (backward_association_id)");
        $this->exec("ALTER TABLE `associated_product` ADD owner_user_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD assigned_user_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("CREATE INDEX IDX_OWNER_USER_ID ON `associated_product` (owner_user_id)");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER_ID ON `associated_product` (assigned_user_id)");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
