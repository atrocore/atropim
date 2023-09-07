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

use Treo\Core\Migration\Base;

class V1Dot5Dot36 extends Base
{
    public function up(): void
    {
        $this->exec("CREATE TABLE `attribute_hierarchy` (`id` INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE utf8mb4_unicode_ci, `entity_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `parent_id` VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `hierarchy_sort_order` INT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, INDEX `IDX_475B582881257D5D` (entity_id), INDEX `IDX_475B5828727ACA70` (parent_id), UNIQUE INDEX `UNIQ_475B582881257D5D727ACA70` (entity_id, parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->exec("ALTER TABLE `attribute` ADD sort_order_in_attribute_group INT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        $this->exec("DROP TABLE `attribute_hierarchy`");
        $this->exec("ALTER TABLE `attribute` DROP sort_order_in_attribute_group");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
