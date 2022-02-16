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

use Espo\Core\Exceptions\Error;
use Treo\Core\Migration\Base;

class V1Dot4Dot9 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE `category_asset` ADD is_main_image TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci");

        $query = "SELECT c.id, a.id as assetId 
                  FROM `category` c
                  LEFT JOIN `asset` a ON a.file_id=c.image_id
                  WHERE c.deleted=0 
                    AND c.image_id IS NOT NULL";

        try {
            $records = $this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $records = [];
        }

        foreach ($records as $record) {
            $this->exec("UPDATE `category_asset` SET is_main_image=1 WHERE deleted=0 AND category_id='{$record['id']}' AND asset_id='{$record['assetId']}'");
        }

        $this->exec("ALTER TABLE `category` DROP image_id");

        $this->exec("ALTER TABLE `product_asset` ADD is_main_image TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci");
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
