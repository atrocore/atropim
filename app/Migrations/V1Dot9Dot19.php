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

class V1Dot9Dot19 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM product_asset WHERE deleted=1");
        $this->getPDO()->exec("DELETE FROM product_asset WHERE scope='Channel' AND (channel_id IS NULL OR channel_id='')");
        $this->exec("DROP INDEX IDX_UNIQUE_RELATIONSHIP ON product_asset");
        $this->getPDO()->exec("UPDATE product_asset SET channel_id='' WHERE channel_id IS NULL");
        $this->exec("CREATE UNIQUE INDEX IDX_UNIQUE_RELATIONSHIP ON product_asset (deleted, product_id, asset_id, scope, channel_id)");
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
