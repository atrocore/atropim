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

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot9Dot35 extends Base
{
    public function up(): void
    {
        try {
            $attributes = $this->getPDO()->query("SELECT * FROM `attribute` WHERE max_length IS NOT NULL AND deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $attributes = [];
        }

        foreach ($attributes as $attribute) {
            $data = @json_decode((string)$attribute['data'], true);
            if (!is_array($data)) {
                $data = [];
            }
            $data['field']['maxLength'] = $attribute['max_length'];
            $this->exec("UPDATE `attribute` SET data='" . json_encode($data) . "' WHERE id='{$attribute['id']}'");
        }

        $this->exec("ALTER TABLE attribute DROP max_length");

        $this->updateComposer("atrocore/pim", "^1.9.35");
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