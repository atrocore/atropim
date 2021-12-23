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

class V1Dot3Dot16 extends Base
{
    public function up(): void
    {
        $this->exec(
            "CREATE TABLE `product_attribute_value_data` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `bool_value` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, `date_value` DATE DEFAULT NULL COLLATE utf8mb4_unicode_ci, `datetime_value` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `int_value` INT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `float_value` DOUBLE PRECISION DEFAULT NULL COLLATE utf8mb4_unicode_ci, `varchar_value` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `text_value` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_BOOL_VALUE` (bool_value, deleted), INDEX `IDX_DATE_VALUE` (date_value, deleted), INDEX `IDX_DATETIME_VALUE` (datetime_value, deleted), INDEX `IDX_INT_VALUE` (int_value, deleted), INDEX `IDX_FLOAT_VALUE` (float_value, deleted), INDEX `IDX_VARCHAR_VALUE` (varchar_value, deleted), INDEX `IDX_TEXT_VALUE` (text_value(500), deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );
    }

    public function down(): void
    {
        $this->exec("DROP TABLE `product_attribute_value_data`");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
