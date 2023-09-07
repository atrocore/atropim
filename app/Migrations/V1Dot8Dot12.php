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

class V1Dot8Dot12 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE product_attribute_value ADD int_value1 INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_INT_VALUE1 ON product_attribute_value (int_value1, deleted)");

        $this->exec("ALTER TABLE product_attribute_value ADD float_value1 DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON product_attribute_value (float_value, deleted)");
    }

    public function down(): void
    {
        $this->exec("DROP INDEX IDX_INT_VALUE1 ON product_attribute_value");
        $this->exec("ALTER TABLE product_attribute_value DROP int_value1");

        $this->exec("DROP INDEX IDX_FLOAT_VALUE1 ON product_attribute_value");
        $this->exec("ALTER TABLE product_attribute_value DROP float_value1");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
