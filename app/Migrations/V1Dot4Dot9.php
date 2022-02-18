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

        $this->exec("DROP INDEX UNIQ_A3F321005DA19414584665AA2F98E47A2F98E47 ON `product_asset`");
        $this->exec("DROP INDEX IDX_A3F32100A2F98E47 ON `product_asset`");
        $this->exec("ALTER TABLE `product_asset` CHANGE `channel` channel VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_asset` ADD main_image_for_channel MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci COMMENT '(DC2Type:array)'");
        $this->exec("CREATE UNIQUE INDEX UNIQ_A3F321005DA19414584665A ON `product_asset` (asset_id, product_id)");

        $query = "SELECT id, data 
                  FROM `product` 
                  WHERE deleted=0 
                    AND data IS NOT NULL 
                    AND data!='{\"mainImages\":[]}'";
        foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_ASSOC) as $product) {
            $productData = @json_decode((string)$product['data'], true);
            if (!empty($productData['mainImages'])) {
                foreach ($productData['mainImages'] as $mainImage) {
                    $query = "SELECT pa.* 
                              FROM `product_asset` pa 
                              LEFT JOIN asset a on a.id = pa.asset_id 
                              WHERE pa.product_id='{$product['id']}' 
                                AND pa.deleted=0 
                                AND a.file_id='{$mainImage['attachmentId']}'";

                    if (!empty($productAsset = $this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC))) {
                        $this->getPDO()->exec("UPDATE `product_asset` SET is_main_image=1 WHERE id={$productAsset['id']}");
                        if ($mainImage['scope'] !== 'Global' && empty($productAsset['channel'])) {
                            $mainImageForChannel = @json_decode((string)$productAsset['main_image_for_channel'], true);
                            if (empty($mainImageForChannel)) {
                                $mainImageForChannel = [];
                            }
                            $mainImageForChannel[] = $mainImage['channelId'];
                            $this->getPDO()->exec(
                                "UPDATE `product_asset` SET main_image_for_channel='" . json_encode(array_unique($mainImageForChannel)) . "' WHERE id={$productAsset['id']}"
                            );
                        }
                    }
                }
            }
        }
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
