<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot8Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("DROP TABLE IF EXISTS classification");
        $this->exec("ALTER TABLE product_family RENAME classification");

        $this->exec("DROP TABLE IF EXISTS classification_attribute");
        $this->exec("ALTER TABLE product_family_attribute RENAME classification_attribute");

        $this->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON classification_attribute");
        $this->exec("DROP INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute");

        $this->exec("ALTER TABLE classification_attribute CHANGE product_family_id classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_CLASSIFICATION_ID ON classification_attribute (classification_id)");
        $this->exec("CREATE UNIQUE INDEX UNIQ_9194286CEB3B4E332A86559FB6E62EFAD4DB71B5AF55D372F5A1AA ON classification_attribute (deleted, classification_id, attribute_id, language, scope, channel_id)");

        $this->exec("DROP TABLE IF EXISTS product_classification");
        $this->exec("CREATE TABLE product_classification (id INT AUTO_INCREMENT NOT NULL UNIQUE COLLATE `utf8mb4_unicode_ci`, classification_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, product_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, deleted TINYINT(1) DEFAULT '0' COLLATE `utf8mb4_unicode_ci`, INDEX IDX_1602AC1D2A86559F (classification_id), INDEX IDX_1602AC1D4584665A (product_id), UNIQUE INDEX UNIQ_1602AC1D2A86559F4584665A (classification_id, product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");

        $this->exec("DROP INDEX id ON product_classification");

        $offset = 0;
        $limit = 2000;
        while (true) {
            $products = $this->getPDO()
                ->query("SELECT * FROM product WHERE product_family_id IS NOT NULL AND deleted=0 ORDER BY id LIMIT $limit OFFSET $offset")
                ->fetchAll(\PDO::FETCH_ASSOC);

            $offset = $offset + $limit;

            if (empty($products)) {
                break;
            }

            foreach ($products as $product) {
                $this->exec("INSERT INTO product_classification (product_id, classification_id) VALUES ('{$product['id']}', '{$product['product_family_id']}')");
            }
        }

        $this->exec("DROP INDEX IDX_PRODUCT_FAMILY_ID ON product");
        $this->exec("ALTER TABLE product DROP product_family_id");

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

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
