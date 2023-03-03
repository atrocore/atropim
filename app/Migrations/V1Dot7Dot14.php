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

class V1Dot7Dot14 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_family_attribute");
        $this->exec("CREATE UNIQUE INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON product_family_attribute (deleted, product_family_id, attribute_id, language, scope, channel_id)");

        $this->exec("DROP INDEX UNIQ_C803FBE9EFB9C8A57D7C1239CF496EEAEB3B4E33 ON associated_product");
        $this->exec("CREATE UNIQUE INDEX UNIQ_C803FBE9EB3B4E33EFB9C8A57D7C1239CF496EEA ON associated_product (deleted, association_id, main_product_id, related_product_id)");

        $this->exec("DROP INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_attribute_value");
        $this->exec("CREATE UNIQUE INDEX UNIQ_CCC4BE1FEB3B4E334584665AB6E62EFAD4DB71B5AF55D372F5A1AA ON product_attribute_value (deleted, product_id, attribute_id, language, scope, channel_id)");

        foreach ($this->getPDO()->query("SELECT * FROM `category_asset`")->fetchAll(\PDO::FETCH_ASSOC) as $v) {
            $this->exec("DELETE FROM `category_asset` WHERE asset_id='{$v['asset_id']}' AND category_id='{$v['category_id']}' AND id!='{$v['id']}'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_EA9C1515EB3B4E3312469DE25DA1941 ON category_asset (deleted, category_id, asset_id)");

        foreach ($this->getPDO()->query("SELECT * FROM `product_asset`")->fetchAll(\PDO::FETCH_ASSOC) as $v) {
            $this->exec("DELETE FROM `product_asset` WHERE asset_id='{$v['asset_id']}' AND product_id='{$v['product_id']}' AND id!='{$v['id']}'");
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_A3F32100EB3B4E334584665A5DA1941 ON product_asset (deleted, product_id, asset_id)");
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
