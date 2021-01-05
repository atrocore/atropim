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

/**
 * Class V1Dot1Dot2
 *
 * @package Pim\Migrations
 */
class V1Dot1Dot2 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $pcs = $this->getPDO()->query(
            "SELECT category_id, product_id, sorting
            FROM product_category
            WHERE deleted = 0
            ORDER BY sorting ASC"
        )->fetchAll(\PDO::FETCH_ASSOC|\PDO::FETCH_GROUP);

        if (!empty($pcs)) {
            foreach ($pcs as $categoryId => $data) {
                $sql = "";
                $sorting = 0;

                foreach ($data as $key => $pc) {
                    $sql .= "UPDATE product_category SET sorting='{$sorting}' WHERE category_id='{$categoryId}' AND product_id='{$pc['product_id']}';";
                    $sorting += 10;
                }

                $this->getPDO()->exec($sql);
            }
        }
    }
}
