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

use Atro\Core\Migration\Base;

class V1Dot5Dot68 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->exec("ALTER TABLE attribute ADD sort_order_in_product INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->exec("ALTER TABLE attribute DROP sort_order_in_product");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
