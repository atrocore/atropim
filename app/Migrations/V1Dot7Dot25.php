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

class V1Dot7Dot25 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE product_attribute_value ADD is_variant_specific_attribute TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`;");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE product_attribute_value DROP is_variant_specific_attribute;");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
