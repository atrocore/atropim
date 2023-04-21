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

class V1Dot8Dot3 extends Base
{
    public function up(): void
    {
//        $this->execute("ALTER TABLE attribute ADD extensible_enum_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
//        $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_ID ON attribute (extensible_enum_id)");

        $this->getPDO()->exec("DELETE FROM extensible_enum WHERE deleted=1");
        $this->getPDO()->exec("DELETE FROM extensible_enum_option WHERE deleted=1");

        $records = $this->getPDO()->query("SELECT a.* FROM attribute a WHERE a.type IN ('enum','multiEnum') AND a.deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $ids = @json_decode($record['type_value_ids'], true);
            if (!empty($ids)) {
                $values = @json_decode((string)$record['type_value'], true);
                if (!empty($values)) {
                    $this->execute("INSERT INTO extensible_enum (id,name) VALUES ('{$record['id']}','{$record['name']}')");
                    foreach ($ids as $k => $id) {
                        $value = isset($values[$k]) ? "'" . $values[$k] . "'" : 'NULL';
                        $sortOrder = $k * 10;
                        $this->execute("INSERT INTO extensible_enum_option (id,extensible_enum_id,name,sort_order) VALUES ('{$record['id']}_{$id}','{$record['id']}',$value, $sortOrder)");
                    }

                    if (!empty($this->getConfig()->get('isMultilangActive'))) {
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
                            $locale = strtolower($v);
                            $languageValues = @json_decode((string)$record['type_value_' . $locale], true);
                            if (!empty($languageValues)) {
                                foreach ($ids as $k => $id) {
                                    $languageValue = isset($languageValues[$k]) ? "'" . $languageValues[$k] . "'" : 'NULL';
                                    $this->execute("UPDATE extensible_enum_option SET name_{$locale}={$languageValue} WHERE id='{$record['id']}_{$id}'");
                                }
                            }
                        }
                    }

                    $this->getPDO()->exec("UPDATE attribute SET extensible_enum_id='{$record['id']}' WHERE id='{$record['id']}'");
                }
            }
        }
    }

    public function down(): void
    {
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}