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

class V1Dot13Dot15 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-29 10:00:00');
    }

    public function up(): void
    {
        foreach (["Category", "Product"] as $entity) {
            $fileName = "custom/Espo/Custom/Resources/layouts/$entity/detail.json";
            if (file_exists($fileName)) {
                $contents = file_get_contents($fileName);
                if (strpos($contents, '"parents"') !== false) {
                    $contents = str_replace('"parents"', '"parent"', $contents);
                    file_put_contents($fileName, $contents);
                }
            }

            $fileName = "custom/Espo/Custom/Resources/layouts/$entity/detailSmall.json";
            if (file_exists($fileName)) {
                $contents = file_get_contents($fileName);
                if (strpos($contents, '"parents"') !== false) {
                    $contents = str_replace('"parents"', '"parent"', $contents);
                    file_put_contents($fileName, $contents);
                }
            }

//            $fileName = "custom/Espo/Custom/Resources/layouts/$entity/relationships.json";
//            if (file_exists($fileName)) {
//                $data = @json_decode(file_get_contents($fileName), true);
//                $newData = [];
//                foreach ($data as $row) {
//                    if (isset($row['name']) && $row['name'] === 'parents') {
//                        continue;
//                    }
//                    $newData[] = $row;
//                }
//                file_put_contents($fileName, json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
//            }
        }
    }

    public function down(): void
    {
    }
}
