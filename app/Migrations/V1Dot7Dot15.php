<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot7Dot15 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_family_attribute");
        $this->exec(
            "CREATE UNIQUE INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON product_family_attribute (deleted, product_family_id, attribute_id, language, scope, channel_id)"
        );

        $this->exec("DROP INDEX UNIQ_C803FBE9EFB9C8A57D7C1239CF496EEAEB3B4E33 ON associated_product");
        $this->exec("CREATE UNIQUE INDEX UNIQ_C803FBE9EB3B4E33EFB9C8A57D7C1239CF496EEA ON associated_product (deleted, association_id, main_product_id, related_product_id)");

        $this->exec("DROP INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_attribute_value");
        $this->exec(
            "CREATE UNIQUE INDEX UNIQ_CCC4BE1FEB3B4E334584665AB6E62EFAD4DB71B5AF55D372F5A1AA ON product_attribute_value (deleted, product_id, attribute_id, language, scope, channel_id)"
        );

        $this->getPDO()->exec("DELETE FROM `category_asset` WHERE deleted=1");
        $duplicates = $this
            ->getPDO()
            ->query("SELECT category_id, asset_id FROM category_asset GROUP BY category_id, asset_id HAVING count(*) > 1")
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($duplicates as $duplicate) {
            $records = $this
                ->getPDO()
                ->query("SELECT * FROM category_asset WHERE asset_id='{$duplicate['asset_id']}' AND category_id='{$duplicate['category_id']}' ORDER BY id")
                ->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($records as $k => $v) {
                if ($k === 0) {
                    continue;
                }
                $this->getPDO()->exec("DELETE FROM `category_asset` WHERE id='{$v['id']}'");
            }
        }
        $this->exec("CREATE UNIQUE INDEX UNIQ_EA9C1515EB3B4E3312469DE25DA1941 ON category_asset (deleted, category_id, asset_id)");

        $this->getPDO()->exec("DELETE FROM `product_asset` WHERE deleted=1");
        $duplicates = $this
            ->getPDO()
            ->query("SELECT product_id, asset_id FROM product_asset GROUP BY product_id, asset_id HAVING count(*) > 1")
            ->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($duplicates as $duplicate) {
            $records = $this
                ->getPDO()
                ->query("SELECT * FROM product_asset WHERE asset_id='{$duplicate['asset_id']}' AND product_id='{$duplicate['product_id']}' ORDER BY id")
                ->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($records as $k => $v) {
                if ($k === 0) {
                    continue;
                }
                $this->getPDO()->exec("DELETE FROM `product_asset` WHERE id='{$v['id']}'");
            }
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
