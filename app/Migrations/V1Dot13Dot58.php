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

use Atro\Core\Exceptions\Exception;
use Atro\Core\Migration\Base;

class V1Dot13Dot58 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-03 12:00:00');
    }

    public function up(): void
    {
        $row = $this->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('catalog_category')
            ->setMaxResults(1)
            ->fetchAssociative();

        if (!empty($row)) {
            $path = "data/metadata/entityDefs/Category.json";
            if (file_exists($path)) {
                $defs = @json_decode(file_get_contents($path), true);
            }
            if (empty($defs)) {
                $defs = [];
            }
            $defs['fields']['catalogs'] = [
                "type"     => "linkMultiple",
                "noLoad"   => true,
                "isCustom" => true
            ];
            $defs['links']['catalogs'] = [
                "type"         => "hasMany",
                "relationName" => "catalogCategory",
                "foreign"      => "categories",
                "entity"       => "Catalog",
                "isCustom"     => true
            ];

            file_put_contents($path, json_encode($defs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $path = "data/metadata/entityDefs/Catalog.json";
            $defs = null;
            if (file_exists($path)) {
                $defs = @json_decode(file_get_contents($path), true);
            }
            if (empty($defs)) {
                $defs = [];
            }
            $defs['fields']['categories'] = [
                "type"     => "linkMultiple",
                "noLoad"   => true,
                "isCustom" => true
            ];
            $defs['links']['categories'] = [
                "type"         => "hasMany",
                "relationName" => "catalogCategory",
                "foreign"      => "catalogs",
                "entity"       => "Category",
                "isCustom"     => true
            ];

            file_put_contents($path, json_encode($defs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->exec("Drop Table catalog_category;");
        }
    }

    public function down(): void
    {
        throw new Exception("Downgrade prohibited");
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
