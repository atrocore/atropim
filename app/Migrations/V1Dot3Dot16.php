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

class V1Dot3Dot16 extends Base
{
    public function up(): void
    {
        $this->exec(
            "CREATE TABLE `product_attribute_value_data` (`id` VARCHAR(24) NOT NULL COLLATE utf8mb4_unicode_ci, `deleted` TINYINT(1) DEFAULT '0' COLLATE utf8mb4_unicode_ci, `bool_value` TINYINT(1) DEFAULT '0' NOT NULL COLLATE utf8mb4_unicode_ci, `date_value` DATE DEFAULT NULL COLLATE utf8mb4_unicode_ci, `datetime_value` DATETIME DEFAULT NULL COLLATE utf8mb4_unicode_ci, `int_value` INT DEFAULT NULL COLLATE utf8mb4_unicode_ci, `float_value` DOUBLE PRECISION DEFAULT NULL COLLATE utf8mb4_unicode_ci, `varchar_value` VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, `text_value` MEDIUMTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci, INDEX `IDX_BOOL_VALUE` (bool_value, deleted), INDEX `IDX_DATE_VALUE` (date_value, deleted), INDEX `IDX_DATETIME_VALUE` (datetime_value, deleted), INDEX `IDX_INT_VALUE` (int_value, deleted), INDEX `IDX_FLOAT_VALUE` (float_value, deleted), INDEX `IDX_VARCHAR_VALUE` (varchar_value, deleted), INDEX `IDX_TEXT_VALUE` (text_value(500), deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"
        );

        $languages = [''];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $languages[] = '_' . strtolower($language);
            }
        }

        foreach ($languages as $language) {
            $this->exec("ALTER TABLE `product_attribute_value` ADD value_data{$language}_id VARCHAR(24) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
            $this->exec("CREATE INDEX IDX_VALUE_DATA" . strtoupper($language) . " ON `product_attribute_value` (value_data{$language}_id)");
        }

        $offset = 0;
        $limit = 1000;
        $query = "SELECT * FROM `product_attribute_value` WHERE deleted=0 ORDER BY id LIMIT %s, %s";

        while (!empty($records = $this->getPDO()->query(sprintf($query, $offset, $limit))->fetchAll(\PDO::FETCH_ASSOC))) {
            $offset = $offset + $limit;

            $attributes = $this
                ->getPDO()
                ->query("SELECT id, type FROM `attribute` WHERE deleted=0 AND id IN ('" . implode("','", array_column($records, 'attribute_id')) . "')")
                ->fetchAll(\PDO::FETCH_ASSOC);
            $attributes = array_column($attributes, 'type', 'id');

            foreach ($records as $record) {
                if (!isset($attributes[$record['attribute_id']])) {
                    $this->exec("DELETE FROM `product_attribute_value` WHERE id='{$record['id']}'");
                    continue 1;
                }

                foreach ($languages as $language) {
                    $dataValues = [];

                    $attributeType = $attributes[$record['attribute_id']];
                    $attributeValue = $record['value' . $language];

                    if ($attributeValue !== null) {
                        switch ($attributeType) {
                            case 'array':
                            case 'multiEnum':
                            case 'text':
                            case 'wysiwyg':
                                $dataValues['text_value'] = $this->getPDO()->quote($attributeValue);
                                break;
                            case 'bool':
                                $dataValues['bool_value'] = !empty($attributeValue) ? 1 : 0;
                                break;
                            case 'currency':
                                $dataValues['float_value'] = (float)$attributeValue;
                                if (!empty($record['data'])) {
                                    $jsonData = @json_decode($record['data'], true);
                                    if (!empty($jsonData['currency'])) {
                                        $dataValues['varchar_value'] = $this->getPDO()->quote($jsonData['currency']);
                                    }
                                }
                                break;
                            case 'unit':
                                $dataValues['float_value'] = (float)$attributeValue;
                                if (!empty($record['data'])) {
                                    $jsonData = @json_decode($record['data'], true);
                                    if (!empty($jsonData['unit'])) {
                                        $dataValues['varchar_value'] = $this->getPDO()->quote($jsonData['unit']);
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
                                    $dataValues['date_value'] = $this->getPDO()->quote($date);
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                                break;
                            case 'datetime':
                                try {
                                    $date = (new \DateTime($attributeValue))->format("Y-m-d H:i:s");
                                    $dataValues['datetime_value'] = $this->getPDO()->quote($date);
                                } catch (\Throwable $e) {
                                    // ignore
                                }
                                break;
                            default:
                                $dataValues['varchar_value'] = $this->getPDO()->quote($attributeValue);
                                break;
                        }
                    }

                    if (!empty($record["value_data{$language}_id"])) {
                        $this->exec("DELETE FROM `product_attribute_value_data` WHERE id='{$record["value_data{$language}_id"]}'");
                    }

                    $dataValueId = Util::generateId();

                    $fields = array_merge(['id'], array_keys($dataValues));
                    $values = array_merge(["'$dataValueId'"], array_values($dataValues));

                    $this->exec("INSERT INTO `product_attribute_value_data` (" . implode(",", $fields) . ") VALUES (" . implode(",", $values) . ")");
                    $this->exec("UPDATE `product_attribute_value` SET value_data{$language}_id='$dataValueId' WHERE id='{$record['id']}'");
                }
            }
        }

        foreach ($languages as $language) {
            $this->exec("ALTER TABLE `product_attribute_value` DROP value{$language}");
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is blocked!');
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
