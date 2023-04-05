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

class V1Dot8Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("ALTER TABLE product_family RENAME classification");
        $this->getPDO()->exec("ALTER TABLE product_family_attribute RENAME classification_attribute");

        $this->getPDO()->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON classification_attribute");
        $this->getPDO()->exec("DROP INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute");

        $this->getPDO()->exec("ALTER TABLE classification_attribute CHANGE product_family_id classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE INDEX IDX_CLASSIFICATION_ID ON classification_attribute (classification_id)");
        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_9194286CEB3B4E332A86559FB6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute (deleted, classification_id, attribute_id, language, scope, channel_id)");

        $this->getPDO()->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON product");
        $this->getPDO()->exec("ALTER TABLE product CHANGE product_family_id classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE INDEX IDX_CLASSIFICATION_ID ON product (classification_id)");
    }

    public function down(): void
    {
        throw new \Exception('Downgrade is prohibited.');
    }
}