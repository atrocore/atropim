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

class V1Dot13Dot63 extends Base
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
            }
            if (empty($defs)) {
                $defs = [];
            }

            if (empty($defs['hasAssignedUser'])) {
                if ($table->hasColumn('assigned_user_id')) {
                    $table->dropColumn('assigned_user_id');
                }

                if ($table->hasIndex('IDX_BRAND_ASSIGNED_USER')) {
                    $table->dropIndex('IDX_BRAND_ASSIGNED_USER');
                }

                if ($table->hasIndex('IDX_BRAND_ASSIGNED_USER_ID')) {
                    $table->dropIndex('IDX_BRAND_ASSIGNED_USER_ID');
                }
            }

            if (empty($defs['hasOwner'])) {
                if ($table->hasColumn('owner_user_id')) {
                    $table->dropColumn('owner_user_id');
                }

                if ($table->hasIndex('IDX_BRAND_OWNER_USER')) {
                    $table->dropIndex('IDX_BRAND_OWNER_USER');
                }

                if ($table->hasIndex('IDX_BRAND_OWNER_USER_ID')) {
                    $table->dropIndex('IDX_BRAND_OWNER_USER_ID');
                }
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
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('brand')) {
            $table = $toSchema->getTable('brand');

            if (!$table->hasColumn('assigned_user_id')) {
                $table->addColumn('assigned_user_id', 'string', ['length' => 36, 'notnull' => false]);
            }

            if (!$table->hasIndex('IDX_BRAND_ASSIGNED_USER_ID')) {
                $table->addIndex(['assigned_user_id', 'deleted'], 'IDX_BRAND_ASSIGNED_USER_ID');
            }

            if (!$table->hasColumn('owner_user_id')) {
                $table->addColumn('owner_user_id', 'string', ['length' => 36, 'notnull' => false]);
            }

            if (!$table->hasIndex('IDX_BRAND_OWNER_USER_ID')) {
                $table->addIndex(['owner_user_id', 'deleted'], 'IDX_BRAND_OWNER_USER_ID');
            }

            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                try {
                    $this->getPDO()->exec($sql);
                } catch (\Exception $exception) {
                }
            }
        }
    }
}
