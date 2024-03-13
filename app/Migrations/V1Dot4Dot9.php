<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

class V1Dot4Dot9 extends Base
{
    public function up(): void
    {
        $this->exec("DELETE FROM `product_asset` WHERE deleted=1");

        // delete duplicates
        foreach ($this->getPDO()->query("SELECT * FROM `product_asset`")->fetchAll(\PDO::FETCH_ASSOC) as $v) {
            $this->exec("DELETE FROM `product_asset` WHERE asset_id='{$v['asset_id']}' AND product_id='{$v['product_id']}' AND id!='{$v['id']}'");
        }

        $this->exec("CREATE TABLE `brand_asset` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `asset_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `brand_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `sorting` INT DEFAULT '100000' COLLATE utf8mb4_unicode_ci, `is_main_image` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_5956934B5DA1941` (asset_id), INDEX `IDX_5956934B44F5D008` (brand_id), UNIQUE INDEX `UNIQ_5956934B5DA194144F5D008` (asset_id, brand_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->exec("DROP INDEX id ON `brand_asset`");

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
