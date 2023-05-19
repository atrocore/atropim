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
    protected array $measures = [];

    public function up(): void
    {
        $this->getPDO()->exec("UPDATE attribute SET type='float' WHERE type='unit'");
        $this->getPDO()->exec("UPDATE product_attribute_value SET attribute_type='float' WHERE attribute_type='unit'");

        $this->getPDO()->exec("ALTER TABLE attribute ADD default_unit VARCHAR(255) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE attribute ADD measure_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE INDEX IDX_MEASURE_ID ON attribute (measure_id)");

        $records = $this->getPDO()
            ->query("SELECT * FROM attribute WHERE data LIKE '%\"measure\"%'")
            ->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($records as $record) {
            $data = @json_decode($record['data'], true);
            if (empty($data['field']['measure'])) {
                continue;
            }

            $measure = $this->getMeasureByName($data['field']['measure']);
            if (empty($measure)) {
                continue;
            }

            $this->getPDO()->exec("UPDATE attribute SET measure_id='{$measure['id']}' WHERE id='{$record['id']}'");

            if (!empty($data['field']['unitDefaultUnit']) && !empty($measure['units'][$data['field']['unitDefaultUnit']])) {
                $this->getPDO()->exec("UPDATE attribute SET default_unit='{$measure['units'][$data['field']['unitDefaultUnit']]}' WHERE id='{$record['id']}'");
            }
        }
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited.');
    }

    protected function getMeasureByName(string $name): array
    {
        if (!isset($this->measures[$name])) {
            $preparedName = $this->getPDO()->quote($name);
            $records = $this->getPDO()
                ->query("SELECT m.id as measureId, u.* FROM measure m LEFT JOIN unit u ON u.measure_id=m.id WHERE m.name=$preparedName AND m.deleted=0")
                ->fetchAll(\PDO::FETCH_ASSOC);

            $this->measures[$name] = [];
            foreach ($records as $v) {
                $this->measures[$name]['id'] = $v['measureId'];
                if (isset($v['id'])) {
                    $this->measures[$name]['units'][$v['name']] = $v['id'];
                }
            }
        }

        return $this->measures[$name];
    }
}
