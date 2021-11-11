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

class V1Dot2Dot19 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM `product_attribute_value` WHERE deleted=1");

        $pavs = $this->getPDO()->query("SELECT * FROM `product_attribute_value`")->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($pavs)) {
            foreach ($pavs as $pav) {
                $where = "id!='{$pav['id']}' AND product_id='{$pav['product_id']}' AND attribute_id='{$pav['attribute_id']}' AND scope='{$pav['scope']}'";
                if ($pav['scope'] === 'Channel') {
                    $where .= "AND channel_id='{$pav['channel_id']}'";
                }
                $this->getPDO()->exec("DELETE FROM `product_attribute_value` WHERE $where");
            }
        }

        try {
            $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAEB3B4E33 ON `product_attribute_value` (product_id, attribute_id, scope, channel_id, deleted)");
        }catch (\Throwable $e){
            // ignore
        }

        $this->getPDO()->exec("UPDATE `product_attribute_value` SET `channel_id`='' WHERE `channel_id` IS NULL");
    }

    public function down(): void
    {
        try {
            $this->getPDO()->exec("DROP INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAEB3B4E33 ON `product_attribute_value`");
        }catch (\Throwable $e){
            // ignore
        }
    }
}
