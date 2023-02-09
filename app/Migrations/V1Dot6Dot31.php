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

use Espo\Core\Exceptions\BadRequest;
use Treo\Core\Migration\Base;

class V1Dot6Dot31 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_A3F321005DA19414584665A ON product_asset");
        $this->exec("ALTER TABLE product_asset ADD created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE id id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE is_main_image is_main_image TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE main_image_for_channel main_image_for_channel LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");

        $this->exec("CREATE INDEX IDX_CREATED_BY_ID ON product_asset (created_by_id)");
        $this->exec("CREATE INDEX IDX_MODIFIED_BY_ID ON product_asset (modified_by_id)");

        $this->exec("ALTER TABLE product_asset RENAME INDEX idx_a3f321004584665a TO IDX_PRODUCT_ID");
        $this->exec("ALTER TABLE product_asset RENAME INDEX idx_a3f321005da1941 TO IDX_ASSET_ID");
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
