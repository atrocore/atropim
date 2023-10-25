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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot9Dot62 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE product_attribute_value ADD reference_value VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE classification_attribute ADD reference_value VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("UPDATE product_attribute_value set reference_value = varchar_value where attribute_type in ('int','float','rangeInt','rangeFloat','asset','link','extensibleEnum')");
        $this->exec("UPDATE product_attribute_value set varchar_value = null where attribute_type in ('int','float','rangeInt','rangeFloat','asset','link','extensibleEnum')");
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}