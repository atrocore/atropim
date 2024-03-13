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

use Atro\Core\Migration\Base;

class V1Dot6Dot1 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE attribute ADD max_length INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_attribute_value ADD max_length INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_family_attribute ADD max_length INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE attribute DROP max_length");
        $this->exec("ALTER TABLE product_attribute_value DROP max_length");
        $this->exec("ALTER TABLE product_family_attribute DROP max_length");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
