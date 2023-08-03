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

class V1Dot9Dot22 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX IDX_FLOAT_VALUE1 ON product_attribute_value");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON product_attribute_value (float_value1, deleted)");

        $this->exec("CREATE INDEX IDX_CREATED_AT ON classification_attribute (created_at, deleted)");
        $this->exec("CREATE INDEX IDX_MODIFIED_AT ON classification_attribute (modified_at, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP bool_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD bool_value TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_BOOL_VALUE ON classification_attribute (bool_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP date_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD date_value DATE DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_DATE_VALUE ON classification_attribute (date_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP datetime_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD datetime_value DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_DATETIME_VALUE ON classification_attribute (datetime_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP int_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD int_value INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_INT_VALUE ON classification_attribute (int_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP int_value1");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD int_value1 INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_INT_VALUE1 ON classification_attribute (int_value1, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP float_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD float_value DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE ON classification_attribute (float_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP float_value1");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD float_value1 DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE1 ON classification_attribute (float_value1, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP varchar_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD varchar_value VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_VARCHAR_VALUE ON classification_attribute (varchar_value, deleted)");

        $this->exec("ALTER TABLE classification_attribute DROP text_value");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD text_value LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_TEXT_VALUE ON classification_attribute (text_value(500), deleted)");

        $this->updateComposer('atrocore/pim', '^1.9.22');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
