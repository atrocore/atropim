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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot7 extends Base
{
    public function up(): void
    {
        $path = "custom/Espo/Custom/Resources/layouts/Product/relationships.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"productAssets"', '"assets"', $contents);
            $contents = str_replace('"productChannels"', '"channels"', $contents);
            file_put_contents($path, $contents);
        }

        $path = "custom/Espo/Custom/Resources/layouts/Assets/relationships.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"productAssets"', '"products"', $contents);
            $contents = str_replace('"categoryAssets"', '"categories"', $contents);
            file_put_contents($path, $contents);
        }

        $path = "custom/Espo/Custom/Resources/layouts/Channels/relationships.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"productChannels"', '"products"', $contents);
            file_put_contents($path, $contents);
        }

        $path = "custom/Espo/Custom/Resources/layouts/Category/relationships.json";
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $contents = str_replace('"categoryAssets"', '"assets"', $contents);
            file_put_contents($path, $contents);
        }

        $path = "custom/Espo/Custom/Resources/layouts/Asset/detailSmall.json";
        if (file_exists($path)) {
            unlink($path);
        }

        $path = "custom/Espo/Custom/Resources/layouts/Channel/detailSmall.json";
        if (file_exists($path)) {
            unlink($path);
        }

        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'product_category', 'main_category', ['type' => 'bool', 'default' => false]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }

        $this->rebuild();
        $this->updateComposer('atrocore/pim', '^1.10.7');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
