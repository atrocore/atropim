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

class V1Dot8Dot9 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM classification WHERE deleted=1");
        $this->exec("ALTER TABLE classification ADD `release` VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");

        while (!empty($id = $this->getDuplicateClassification())) {
            $this->getPDO()->exec("UPDATE classification SET code=NULL WHERE id='$id'");
        }

        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_456BD231EB3B4E339E47031D77153098 ON classification (deleted, `release`, code)");
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited!");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function getDuplicateClassification()
    {
        $query = "SELECT c1.id
                  FROM classification c1
                  JOIN classification c2 ON c1.code=c2.code
                  WHERE c1.deleted=0
                    AND c2.deleted=0
                    AND c1.id!=c2.id
                  ORDER BY c1.id
                  LIMIT 0,1";

        return $this->getPDO()->query($query)->fetch(\PDO::FETCH_COLUMN);
    }
}
