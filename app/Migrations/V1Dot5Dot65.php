<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
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
