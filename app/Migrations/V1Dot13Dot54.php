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

class V1Dot13Dot54 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-20 12:00:00');
    }

    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('attribute')) {
            $table = $toSchema->getTable('attribute');

            if (!$table->hasColumn('html_sanitizer_id')) {
                $table->addColumn('html_sanitizer_id', 'string', ['length' => 36, 'notnull' => false]);

                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->getPDO()->exec($sql);
                }
            }
        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        if ($toSchema->hasTable('attribute')) {
            $table = $toSchema->getTable('attribute');

            if ($table->hasColumn('html_sanitizer_id')) {
                $table->dropColumn('html_sanitizer_id');

                foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                    $this->getPDO()->exec($sql);
                }
            }
        }
    }
}
