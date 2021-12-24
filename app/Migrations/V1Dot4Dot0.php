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

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Treo\Core\Migration\Base;

class V1Dot4Dot0 extends Base
{
    public function up(): void
    {
        $languages = [];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $languages[$language] = strtolower($language);
            }
        }

        $this->exec("DELETE FROM `product_attribute_value` WHERE deleted=1");

        $this->exec("ALTER TABLE `product_attribute_value` ADD language VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD text_value MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD float_value DOUBLE PRECISION DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD varchar_value VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD datetime_value DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD int_value INT DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD bool_value TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD date_value DATE DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        $this->exec("ALTER TABLE `product_attribute_value` ADD attribute_type VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");

        $this->exec("CREATE INDEX IDX_BOOL_VALUE ON `product_attribute_value` (bool_value, deleted)");
        $this->exec("CREATE INDEX IDX_DATE_VALUE ON `product_attribute_value` (date_value, deleted)");
        $this->exec("CREATE INDEX IDX_DATETIME_VALUE ON `product_attribute_value` (datetime_value, deleted)");
        $this->exec("CREATE INDEX IDX_INT_VALUE ON `product_attribute_value` (int_value, deleted)");
        $this->exec("CREATE INDEX IDX_FLOAT_VALUE ON `product_attribute_value` (float_value, deleted)");
        $this->exec("CREATE INDEX IDX_VARCHAR_VALUE ON `product_attribute_value` (varchar_value, deleted)");
        $this->exec("CREATE INDEX IDX_TEXT_VALUE ON `product_attribute_value` (text_value(500), deleted)");
        $this->exec("ALTER TABLE `product` ADD has_inconsistent_attributes TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci");

        $this->exec("DROP INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAEB3B4E33 ON `product_attribute_value`");
        $this->exec("CREATE UNIQUE INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON `product_attribute_value` (product_id, attribute_id, scope, channel_id, language, deleted)");

        $offset = 0;
        $limit = 1000;
        $query = "SELECT * FROM `product_attribute_value` WHERE deleted=0 ORDER BY id LIMIT %s, %s";

        while (!empty($records = $this->getPDO()->query(sprintf($query, $offset, $limit))->fetchAll(\PDO::FETCH_ASSOC))) {
            $offset = $offset + $limit;

            $attrs = $this
                ->getPDO()
                ->query("SELECT id, type, is_multilang FROM `attribute` WHERE deleted=0 AND id IN ('" . implode("','", array_column($records, 'attribute_id')) . "')")
                ->fetchAll(\PDO::FETCH_ASSOC);

            $attributes = [];
            foreach ($attrs as $v) {
                $attributes[$v['id']] = $v;
            }

            foreach ($records as $record) {
                if (!isset($attributes[$record['attribute_id']])) {
                    $this->exec("DELETE FROM `product_attribute_value` WHERE id='{$record['id']}'");
                    continue 1;
                }

                $attributeType = $attributes[$record['attribute_id']]['type'];

                $record['attribute_type'] = $attributeType;
                $this->exec("UPDATE `product_attribute_value` SET attribute_type='$attributeType' WHERE id='{$record['id']}'");

                foreach (array_merge(['main' => ''], $languages) as $locale => $language) {
                    if ($locale !== 'main' && empty($attributes[$record['attribute_id']]['is_multilang'])) {
                        continue;
                    }

                    $dataValues = [];

                    $attributeValue = $record['value' . $language];

                    if ($attributeValue !== null) {
                        switch ($attributeType) {
                            case 'array':
                            case 'multiEnum':
                            case 'text':
                            case 'wysiwyg':
                                $dataValues['text_value'] = (string)$attributeValue;
                                break;
                            case 'bool':
                                $dataValues['bool_value'] = !empty($attributeValue) ? 1 : 0;
                                break;
                            case 'currency':
                                $dataValues['float_value'] = (float)$attributeValue;
                                if (!empty($record['data'])) {
                                    $jsonData = @json_decode($record['data'], true);
                                    if (!empty($jsonData['currency'])) {
                                        $dataValues['varchar_value'] = (string)$jsonData['currency'];
                                    }
                                }
                                break;
                            case 'unit':
                                $dataValues['float_value'] = (float)$attributeValue;
                                if (!empty($record['data'])) {
                                    $jsonData = @json_decode($record['data'], true);
                                    if (!empty($jsonData['unit'])) {
                                        $dataValues['varchar_value'] = (string)$jsonData['unit'];
                                    }
                                }
                                break;
                            case 'int':
                                $dataValues['int_value'] = (int)$attributeValue;
                                break;
                            case 'float':
                                $dataValues['float_value'] = (float)$attributeValue;
                                break;
                            case 'date':
                                try {
                                    $date = (new \DateTime($attributeValue))->format("Y-m-d");
                                    $dataValues['date_value'] = (string)$date;
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                                break;
                            case 'datetime':
                                try {
                                    $date = (new \DateTime($attributeValue))->format("Y-m-d H:i:s");
                                    $dataValues['datetime_value'] = (string)$date;
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                                break;
                            default:
                                $dataValues['varchar_value'] = (string)$attributeValue;
                                break;
                        }
                    }

                    $updateData = array_merge($record, $dataValues);

                    if (isset($updateData["is_inherit_assigned_user_$language"])) {
                        $updateData['is_inherit_assigned_user'] = $updateData["is_inherit_assigned_user_$language"];
                    }
                    if (isset($updateData["is_inherit_owner_user_$language"])) {
                        $updateData['is_inherit_owner_user'] = $updateData["is_inherit_owner_user_$language"];
                    }
                    if (isset($updateData["is_inherit_teams_$language"])) {
                        $updateData['is_inherit_teams'] = $updateData["is_inherit_teams_$language"];
                    }
                    if (isset($updateData["owner_user_{$language}_id"])) {
                        $updateData['owner_user_id'] = $updateData["owner_user_{$language}_id"];
                    }
                    if (isset($updateData["assigned_user_{$language}_id"])) {
                        $updateData['assigned_user_id'] = $updateData["assigned_user_{$language}_id"];
                    }

                    if (!empty($language)) {
                        $updateData['id'] .= '~' . $locale;
                        $this->exec("INSERT INTO `product_attribute_value` (id, language) VALUES ('{$updateData['id']}', '$locale')");
                    }

                    $updateQueryParts = [];
                    foreach ($updateData as $field => $val) {
                        if (in_array($field, ['id', 'deleted']) || $val === null) {
                            continue;
                        }

                        if (is_string($val)) {
                            $val = $this->getPDO()->quote($val);
                        }

                        $updateQueryParts[] = "$field=$val";
                    }

                    $this->exec("UPDATE `product_attribute_value` SET " . implode(",", $updateQueryParts) . " WHERE id='{$updateData['id']}'");
                }
            }
        }

        foreach ($languages as $language) {
            $this->exec("ALTER TABLE `product_attribute_value` DROP value_$language");
            $this->exec("DROP INDEX IDX_OWNER_USER_" . strtoupper($language) . " ON `product_attribute_value`");
            $this->exec("DROP INDEX IDX_ASSIGNED_USER_" . strtoupper($language) . " ON `product_attribute_value`");
            $this->exec("ALTER TABLE `product_attribute_value` DROP is_inherit_assigned_user_$language");
            $this->exec("ALTER TABLE `product_attribute_value` DROP is_inherit_owner_user_$language");
            $this->exec("ALTER TABLE `product_attribute_value` DROP is_inherit_teams_$language");
            $this->exec("ALTER TABLE `product_attribute_value` DROP owner_user_{$language}_id");
            $this->exec("ALTER TABLE `product_attribute_value` DROP assigned_user_{$language}_id");
        }
        $this->exec("ALTER TABLE `product_attribute_value` DROP value");
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
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
