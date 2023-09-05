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

/**
 * Migration class for version 1.5.35
 */
class V1Dot5Dot35 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->exec("ALTER TABLE `attribute` ADD default_scope VARCHAR(255) DEFAULT 'Global' COLLATE utf8mb4_unicode_ci, ADD default_is_required TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, ADD default_channel_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("CREATE INDEX IDX_DEFAULT_CHANNEL_ID ON `attribute` (default_channel_id)");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        $this->exec("DROP INDEX IDX_DEFAULT_CHANNEL_ID ON `attribute`");
        $this->exec("ALTER TABLE `attribute` DROP default_channel_id, DROP default_is_required, DROP default_scope");
    }

    /**
     * @param string $query
     *
     * @return void
     */
    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
