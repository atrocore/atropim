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

class V1Dot13Dot64 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-10 13:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('brand')) {
            $table = $toSchema->getTable('brand');

            $path = "data/metadata/scopes/Brand.json";
            if (file_exists($path)) {
                $defs = @json_decode(file_get_contents($path), true);
                if (empty($defs)) {
                    $defs = [];
                }

                if (!isset($defs['hasAssignedUser'])) {
                    $defs['hasAssignedUser'] = true;
                }

                if (!isset($defs['hasOwner'])) {
                    $defs['hasOwner'] = true;
                }

                if (!isset($defs['hasTeam'])) {
                    $defs['hasTeam'] = true;
                }
            } else {
                $defs = [
                    "hasAssignedUser"   => true,
                    "hasOwner"          => true,
                    "hasTeam"           => true,
                ];
            }

            file_put_contents($path, json_encode($defs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($table->hasIndex('IDX_BRAND_ASSIGNED_USER')) {
                $table->dropIndex('IDX_BRAND_ASSIGNED_USER');
            }

            if ($table->hasIndex('IDX_BRAND_OWNER_USER')) {
                $table->dropIndex('IDX_BRAND_OWNER_USER');
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                try {
                    $this->getPDO()->exec($sql);
                } catch (\Exception $exception) {
                }
            }
        }
    }

    public function down(): void
    {
    }
}
