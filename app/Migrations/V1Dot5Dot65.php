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

class V1Dot5Dot65 extends Base
{
    public function up(): void
    {
        $pavs = $this
            ->getPDO()
            ->query("SELECT id, product_id, channel_id FROM `product_attribute_value` WHERE deleted=0 AND scope='Channel'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($pavs)) {
            return;
        }

        $records = $this
            ->getPDO()
            ->query("SELECT product_id, channel_id FROM `product_channel` WHERE deleted=0 AND product_id IN ('" . implode("','", array_column($pavs, 'product_id')) . "')")
            ->fetchAll(\PDO::FETCH_ASSOC);

        $product = [];
        foreach ($records as $row) {
            $product[$row['product_id']][] = $row['channel_id'];
        }

        foreach ($pavs as $pav) {
            if (!isset($product[$pav['product_id']]) || !in_array($pav['channel_id'], $product[$pav['product_id']])) {
                $this->getPDO()->exec("DELETE FROM `product_attribute_value` WHERE id='{$pav['id']}'");
            }
        }
    }

    public function down(): void
    {
    }
}
