<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot5Dot16 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE `attribute` ADD virtual_product_field TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");

        $path = 'custom/Espo/Custom/Resources/layouts/ProductAttributeValue/listSmall.json';
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE `attribute` DROP virtual_product_field");

        $path = 'custom/Espo/Custom/Resources/layouts/ProductAttributeValue/listSmall.json';
        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
