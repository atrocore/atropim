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

class V1Dot9Dot11 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE attribute DROP use_disabled_textarea_in_view_mode");

        $this->getPDO()->exec("ALTER TABLE attribute ADD use_disabled_textarea_in_view_mode TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE attribute DROP use_disabled_textarea_in_view_mode");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
