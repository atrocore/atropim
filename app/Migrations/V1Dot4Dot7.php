<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Exceptions\Error;
use Atro\Core\Migration\Base;

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
