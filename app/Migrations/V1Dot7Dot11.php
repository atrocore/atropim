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

class V1Dot7Dot11 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE associated_product ADD sorting INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        $limit = 5000;
        $offset = 0;

        while (!empty($ids = $this->getPDO()->query("SELECT id FROM product WHERE deleted=0 LIMIT $limit OFFSET $offset")->fetchAll(\PDO::FETCH_COLUMN))) {
            foreach ($ids as $id) {
                $relationIds = $this->getPDO()->query("SELECT id FROM associated_product WHERE main_product_id='$id' AND deleted=0 ORDER BY sorting")->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($relationIds as $k => $relationId) {
                    $sorting = $k * 10;
                    $this->getPDO()->exec("UPDATE associated_product SET sorting=$sorting WHERE id='$relationId'");
                }
            }
            $offset = $offset + $limit;
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE associated_product DROP sorting");
    }
}
