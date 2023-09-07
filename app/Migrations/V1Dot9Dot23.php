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

class V1Dot9Dot23 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM classification_attribute WHERE channel_id IS NULL");
        $this->getPDO()->exec("DELETE FROM product_attribute_value WHERE channel_id IS NULL");

        $this->getPDO()->exec("ALTER TABLE product_attribute_value CHANGE channel_id channel_id VARCHAR(24) DEFAULT '' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE classification_attribute CHANGE channel_id channel_id VARCHAR(24) DEFAULT '' NOT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->exec("ALTER TABLE product_asset CHANGE channel_id channel_id VARCHAR(24) DEFAULT '' NOT NULL COLLATE `utf8mb4_unicode_ci`");

        $this->updateComposer('atrocore/pim', '^1.9.23');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
