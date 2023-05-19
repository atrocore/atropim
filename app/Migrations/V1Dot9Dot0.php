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

class V1Dot9Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("UPDATE attribute SET type='float' WHERE type='unit'");
        $this->getPDO()->exec("UPDATE product_attribute_value SET attribute_type='float' WHERE attribute_type='unit'");
//        $this->getPDO()->exec("CREATE INDEX IDX_INT_VALUE1 ON product_attribute_value (int_value1, deleted)");
//
//        $this->getPDO()->exec("ALTER TABLE product_attribute_value ADD float_value1 DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
//        $this->getPDO()->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON product_attribute_value (float_value, deleted)");
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited.');
    }
}
