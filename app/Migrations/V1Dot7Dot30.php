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

class V1Dot7Dot30 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE product_attribute_value DROP is_required, DROP max_length");
        $this->exec("ALTER TABLE attribute CHANGE default_is_required is_required TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        throw new \Exception('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
