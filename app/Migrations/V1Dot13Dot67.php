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

class V1Dot13Dot67 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-12-19 17:00:00');
    }

    public function up(): void
    {
        $schema = $this->getCurrentSchema();
        $toSchema = clone $schema;

        $table = $toSchema->getTable('product');
        if (!$table->hasColumn('rrp_unit_id')) {
            $table->addColumn('rrp_unit_id', 'string', ['length' => 36, 'notnull' => false]);
            $table->addIndex(['rrp_unit_id', 'deleted'], 'IDX_PRODUCT_RRP_UNIT_ID');
        }

        foreach ($this->schemasDiffToSql($schema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }
}
