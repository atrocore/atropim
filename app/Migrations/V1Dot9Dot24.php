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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;
class V1Dot9Dot24 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE channel ADD type VARCHAR(255) DEFAULT 'general' COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE channel DROP type");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}