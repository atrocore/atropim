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

class V1Dot8Dot0 extends Base
{
    public function up(): void
    {
        $this->getPDO()->exec("DROP TABLE IF EXISTS classification");
        $this->getPDO()->exec("ALTER TABLE product_family RENAME classification");

        $this->getPDO()->exec("DROP TABLE IF EXISTS classification_attribute");
        $this->getPDO()->exec("ALTER TABLE product_family_attribute RENAME classification_attribute");

        $this->getPDO()->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON classification_attribute");
        $this->getPDO()->exec("DROP INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute");

        $this->getPDO()->exec("ALTER TABLE classification_attribute CHANGE product_family_id classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE INDEX IDX_CLASSIFICATION_ID ON classification_attribute (classification_id)");
        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_9194286CEB3B4E332A86559FB6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute (deleted, classification_id, attribute_id, language, scope, channel_id)");

        $this->getPDO()->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON product");
        $this->getPDO()->exec("ALTER TABLE product CHANGE product_family_id classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("CREATE INDEX IDX_CLASSIFICATION_ID ON product (classification_id)");

        $this->getPDO()->exec("DROP TABLE IF EXISTS product_classification");
        $this->getPDO()->exec("CREATE TABLE product_classification (id INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`, classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, product_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, INDEX IDX_1602AC1D2A86559F (classification_id), INDEX IDX_1602AC1D4584665A (product_id), UNIQUE INDEX UNIQ_1602AC1D2A86559F4584665A (classification_id, product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

        $this->getPDO()->exec("DROP INDEX id ON product_classification");

        $products = $this->getPDO()->query("SELECT * FROM product WHERE classification_id Is NOT NULL AND deleted=0")->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($products as $product){
            $this->getPDO()->exec("INSERT INTO product_classification (product_id, classification_id) VALUES ('{$product['id']}', '{$product['classification_id']}')");
        }

        $this->getPDO()->exec("DROP INDEX IDX_CLASSIFICATION_ID ON product");
        $this->getPDO()->exec("ALTER TABLE product DROP classification_id");

        $this->updateLayout('Product', 'detail', 'productFamily', 'classifications');
        $this->updateConfig();
    }

    public function down(): void
    {
        throw new \Exception('Downgrade is prohibited.');
    }

    protected function updateLayout(string $entityName, string $type, string $was, string $became): void
    {
        $path = "custom/Espo/Custom/Resources/layouts/$entityName/$type.json";
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        $contents = str_replace('"' . $was . '"', '"' . $became . '"', $contents);

        file_put_contents($path, $contents);
    }

    protected function updateConfig(): void
    {
        $path = "data/config.php";
        if (!file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);
        $contents = str_replace("'ProductFamily'", "'Classification'", $contents);
        $contents = str_replace("'ProductFamilyAttribute'", "'ClassificationAttribute'", $contents);
        $contents = str_replace("'behaviorOnProductFamilyChange'", "'behaviorOnClassificationChange'", $contents);

        file_put_contents($path, $contents);
    }
}