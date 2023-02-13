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

class V1Dot7Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_A3F321005DA19414584665A ON product_asset");
        $this->exec("DROP INDEX idx_a3f321004584665a ON product_asset");
        $this->exec("DROP INDEX idx_a3f321005da1941 ON product_asset");

        $this->getPDO()->exec(
            "ALTER TABLE product_asset ADD created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE id id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE is_main_image is_main_image TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE main_image_for_channel main_image_for_channel LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'"
        );

        $this->getPDO()->exec("CREATE INDEX IDX_CREATED_BY_ID ON product_asset (created_by_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_MODIFIED_BY_ID ON product_asset (modified_by_id)");

        $this->getPDO()->exec("CREATE INDEX IDX_PRODUCT_ID ON product_asset (product_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_ASSET_ID ON product_asset (asset_id)");

        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_A3F32100EB3B4E334584665A5DA1941 ON product_asset (deleted, product_id, asset_id)");

        $this->getPDO()->exec("DELETE FROM product_asset WHERE deleted=0 AND asset_id NOT IN (SELECT id FROM asset WHERE deleted=0)");

        $this->getPDO()->exec("ALTER TABLE product_asset CHANGE channel channel_id varchar(24) null");
        $this->getPDO()->exec("CREATE INDEX IDX_CHANNEL_ID ON product_asset (channel_id)");

        $this->getPDO()->exec("ALTER TABLE product_asset DROP main_image_for_channel");

        $this->getPDO()->exec("ALTER TABLE product_asset ADD tags LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");

        $this->getPDO()->exec("ALTER TABLE product_asset ADD scope VARCHAR(255) DEFAULT 'Global' COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec("UPDATE product_asset SET scope='Channel' WHERE channel_id IS NOT NULL AND channel_id!=''");

        try {
            /** @var \Espo\Core\Utils\Layout $layoutManager */
            $layoutManager = (new \Espo\Core\Application())->getContainer()->get('layout');
            $layoutManager->set(json_decode(str_replace('"assets"', '"productAssets"', $layoutManager->get('Product', 'relationships'))), 'Product', 'relationships');
            $layoutManager->save();
        } catch (\Throwable $e) {
        }
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
