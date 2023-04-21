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
        $records = $this->getPDO()
            ->query("SELECT a.* FROM attribute a WHERE a.type IN ('unit') AND a.deleted=0")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            $data = @json_decode((string)$record['data'], true);
            $typeValue = @json_decode((string)$record['type_value'], true);
            if (!empty($typeValue[0]) && empty($data['field']['measure'])) {
                $data['field']['measure'] = $typeValue[0];
                $this->getPDO()->exec("UPDATE attribute set attribute.data='" . json_encode($data) . "' WHERE id='{$record['id']}'");
            }
        }

        $this->execute("ALTER TABLE attribute ADD extensible_enum_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->execute("CREATE INDEX IDX_EXTENSIBLE_ENUM_ID ON attribute (extensible_enum_id)");

        $records = $this->getPDO()->query("SELECT a.* FROM attribute a WHERE a.type IN ('enum','multiEnum') AND a.deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($records as $record) {
            $ids = @json_decode($record['type_value_ids'], true);
            if (!empty($ids)) {
                $values = @json_decode((string)$record['type_value'], true);
                if (!empty($values)) {
                    $this->execute("DELETE FROM extensible_enum WHERE id='{$record['id']}';INSERT INTO extensible_enum (id,name) VALUES ('{$record['id']}','{$record['name']}')");

                    foreach ($ids as $k => $id) {
                        $value = isset($values[$k]) ? "'" . $values[$k] . "'" : 'NULL';
                        $sortOrder = $k * 10;

                        $optionId = $this->generateOptionId((string)$record['id'], (string)$id);
                        $optionsIds["{$record['id']}_{$id}"] = $optionId;

                        $this->execute(
                            "DELETE FROM extensible_enum_option WHERE id='{$optionId}';INSERT INTO extensible_enum_option (id,extensible_enum_id,name,sort_order) VALUES ('$optionId','{$record['id']}',$value, $sortOrder)"
                        );

                        // migrate pavs for enum
                        if ($record['type'] === 'enum') {
                            $this->getPDO()->exec("UPDATE product_attribute_value SET varchar_value='{$optionId}' WHERE attribute_id='{$record['id']}' AND  varchar_value='{$id}'");
                            $this->getPDO()->exec("UPDATE attribute SET enum_default='{$optionId}' WHERE enum_default='{$id}'");
                        }
                    }

                    // migrate pavs for multiEnum
                    if ($record['type'] === 'multiEnum') {
                        $limit = 2000;
                        $offset = 0;

                        while (true) {
                            $pavs = $this->getPDO()
                                ->query("SELECT * FROM product_attribute_value WHERE attribute_id='{$record['id']}' AND deleted=0 ORDER BY id LIMIT $limit OFFSET $offset")
                                ->fetchAll(\PDO::FETCH_ASSOC);

                            $offset = $offset + $limit;

                            if (empty($pavs)) {
                                break;
                            }

                            foreach ($pavs as $pav) {
                                $pavValues = @json_decode((string)$pav['text_value'], true);
                                if (!empty($pavValues)) {
                                    $newPavValues = [];
                                    foreach ($pavValues as $pavValue) {
                                        if (!isset($optionsIds["{$record['id']}_{$pavValue}"])) {
                                            continue 2;
                                        }
                                        $newPavValues[] = $optionsIds["{$record['id']}_{$pavValue}"];
                                    }

                                    $newTextValue = json_encode($newPavValues);
                                    $this->getPDO()->exec("UPDATE product_attribute_value SET text_value='{$newTextValue}' WHERE id='{$pav['id']}'");
                                }
                            }
                        }
                    }

                    if (!empty($this->getConfig()->get('isMultilangActive'))) {
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
                            $locale = strtolower($v);
                            $languageValues = @json_decode((string)$record['type_value_' . $locale], true);
                            if (!empty($languageValues)) {
                                foreach ($ids as $k => $id) {
                                    $optionId = $this->generateOptionId((string)$record['id'], (string)$id);
                                    $languageValue = isset($languageValues[$k]) ? "'" . $languageValues[$k] . "'" : 'NULL';
                                    $this->execute("UPDATE extensible_enum_option SET name_{$locale}={$languageValue} WHERE id='{$optionId}'");
                                }
                            }
                        }
                    }

                    $this->getPDO()->exec("UPDATE attribute SET extensible_enum_id='{$record['id']}' WHERE id='{$record['id']}'");
                }
            }
        }

        $this->execute("ALTER TABLE attribute DROP type_value_ids,");
        $this->execute("ALTER TABLE attribute DROP type_value");
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
                $locale = strtolower($v);
                $this->execute("ALTER TABLE attribute DROP type_value_{$locale}");
            }
        }
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited!');
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }

    protected function generateOptionId(string $attributeId, string $typeValueId): string
    {
        return substr(md5("{$attributeId}_{$typeValueId}"), 0, 17);
    }
}