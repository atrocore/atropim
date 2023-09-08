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

use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Migration\Base;

class V1Dot7Dot0 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_A3F321005DA19414584665A ON product_asset");
        $this->exec("DROP INDEX idx_a3f321004584665a ON product_asset");
        $this->exec("DROP INDEX idx_a3f321005da1941 ON product_asset");

        $this->getPDO()->exec(
            "ALTER TABLE product_asset ADD created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE id id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE is_main_image is_main_image TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE main_image_for_channel main_image_for_channel LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'"
        );

        $this->getPDO()->exec("CREATE INDEX IDX_CREATED_BY_ID ON product_asset (created_by_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_MODIFIED_BY_ID ON product_asset (modified_by_id)");

        $this->getPDO()->exec("CREATE INDEX IDX_PRODUCT_ID ON product_asset (product_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_ASSET_ID ON product_asset (asset_id)");

        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_A3F32100EB3B4E334584665A5DA1941 ON product_asset (deleted, product_id, asset_id)");

        $this->getPDO()->exec("DELETE FROM product_asset WHERE deleted=0 AND asset_id NOT IN (SELECT id FROM asset WHERE deleted=0)");

        $this->getPDO()->exec("ALTER TABLE product_asset CHANGE channel channel_id varchar(24) null");
        $this->getPDO()->exec("CREATE INDEX IDX_CHANNEL_ID ON product_asset (channel_id)");

        $this->getPDO()->exec("ALTER TABLE product_asset DROP main_image_for_channel");

        $this->getPDO()->exec("ALTER TABLE product_asset ADD tags LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");

        $this->getPDO()->exec("ALTER TABLE product_asset ADD scope VARCHAR(255) DEFAULT 'Global' COLLATE `utf8mb4_unicode_ci`");

        $this->getPDO()->exec("UPDATE product_asset SET scope='Channel' WHERE channel_id IS NOT NULL AND channel_id!=''");

        $this->exec("DROP INDEX UNIQ_EA9C15155DA194112469DE2 ON category_asset");
        $this->getPDO()->exec(
            "ALTER TABLE category_asset ADD tags LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)', ADD created_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_at DATETIME DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD created_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD modified_by_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE id id VARCHAR(24) NOT NULL COLLATE `utf8mb4_unicode_ci`, CHANGE is_main_image is_main_image TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`"
        );

        $this->getPDO()->exec("CREATE INDEX IDX_CREATED_BY_ID ON category_asset (created_by_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_MODIFIED_BY_ID ON category_asset (modified_by_id)");

        $this->getPDO()->exec("CREATE UNIQUE INDEX UNIQ_EA9C1515EB3B4E3312469DE25DA1941 ON category_asset (deleted, category_id, asset_id)");

        $this->exec("DROP INDEX idx_ea9c151512469de2 ON category_asset");
        $this->exec("DROP INDEX idx_ea9c15155da1941 ON category_asset");

        $this->getPDO()->exec("CREATE INDEX IDX_CATEGORY_ID ON category_asset (category_id)");
        $this->getPDO()->exec("CREATE INDEX IDX_ASSET_ID ON category_asset (asset_id)");

        try {
            /** @var \Espo\Core\Utils\Layout $layoutManager */
            $layoutManager = (new \Espo\Core\Application())->getContainer()->get('layout');
            $layoutManager->set(json_decode(str_replace('"assets"', '"productAssets"', $layoutManager->get('Product', 'relationships'))), 'Product', 'relationships');
            $layoutManager->set(json_decode(str_replace('"products"', '"productAssets"', $layoutManager->get('Asset', 'relationships'))), 'Asset', 'relationships');
            $layoutManager->set(json_decode(str_replace('"assets"', '"categoryAssets"', $layoutManager->get('Category', 'relationships'))), 'Category', 'relationships');
            $layoutManager->set(json_decode(str_replace('"categories"', '"categoryAssets"', $layoutManager->get('Asset', 'relationships'))), 'Asset', 'relationships');
            $layoutManager->save();
        } catch (\Throwable $e) {
        }

        try {
            /** @var \Espo\Core\Utils\Metadata $metadata */
            $metadata = (new \Espo\Core\Application())->getContainer()->get('metadata');
            $metadata->delete('entityDefs', 'Product', ['fields.assets', 'links.assets']);
            $metadata->delete('entityDefs', 'Asset', ['fields.products', 'links.products']);
            $metadata->delete('entityDefs', 'Category', ['fields.assets', 'links.assets']);
            $metadata->delete('entityDefs', 'Asset', ['fields.categories', 'links.categories']);
            $metadata->save();
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
