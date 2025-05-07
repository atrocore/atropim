<?php
/*
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

class V1Dot14Dot3 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-09 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX idx_product_attribute_value_channel_id");
            $this->exec("DROP INDEX idx_product_attribute_value_modified_at");
            $this->exec("DROP INDEX idx_product_attribute_value_created_by_id");
            $this->exec("DROP INDEX idx_product_attribute_value_modified_by_id");
            $this->exec("DROP INDEX idx_product_attribute_value_created_at");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP");

            $this->exec("ALTER TABLE product_attribute_value ADD json_value TEXT DEFAULT NULL");
            $this->exec("COMMENT ON COLUMN product_attribute_value.json_value IS '(DC2Type:jsonObject)'");
        } else {
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_MODIFIED_BY_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_MODIFIED_AT ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CHANNEL_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CREATED_AT ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_CREATED_BY_ID ON product_attribute_value");
            $this->exec("DROP INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value");

            $this->exec("ALTER TABLE product_attribute_value ADD json_value LONGTEXT DEFAULT NULL COMMENT '(DC2Type:jsonObject)'");
        }

        foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
            $this->exec("ALTER TABLE product_attribute_value ADD varchar_value_" . strtolower($language) . " VARCHAR(255) DEFAULT NULL");
            $this->exec("ALTER TABLE product_attribute_value ADD text_value_" . strtolower($language) . " VARCHAR(255) DEFAULT NULL");
        }

        // migrate data

        $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_ATTRIBUTE_VALUE_UNIQUE_RELATIONSHIP ON product_attribute_value (deleted, product_id, attribute_id)");
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
