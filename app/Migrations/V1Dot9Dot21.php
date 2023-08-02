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

class V1Dot9Dot21 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE classification_attribute DROP `default`");

        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD `default` LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_from DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_to DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_currency VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_unit_id VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_unit VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_all_units LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonObject)', ADD default_value_id VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_name VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD default_value_option_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)', ADD default_value_paths_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonObject)', ADD default_value_names LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)', ADD default_value_options_data LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE classification_attribute DROP `default`, DROP default_value_from, DROP default_value_to, DROP default_value_currency, DROP default_value_unit_id, DROP default_value_unit, DROP default_value_all_units, DROP default_value_id, DROP default_value_name, DROP default_value_option_data, DROP default_value_paths_data, DROP default_value_names, DROP default_value_options_data");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

}
