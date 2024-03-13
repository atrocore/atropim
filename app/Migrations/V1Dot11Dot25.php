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
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

class V1Dot11Dot25 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;
        $tableName = 'classification_attribute_extensible_enum_option';
        if(!$toSchema->hasTable($tableName)){

            $toSchema->createTable($tableName);
            $this->addColumn($toSchema, $tableName, 'id', ['type' => 'varchar', 'len' => 24,'notNull' => true, 'primary' => true]);
            $this->addColumn($toSchema, $tableName, 'deleted', ['type' => 'bool', 'default' => false]);
            $this->addColumn($toSchema, $tableName, 'created_at', ['type' => 'datetime', 'len' => 24, 'default' => null]);
            $this->addColumn($toSchema, $tableName, 'modified_at', ['type' => 'datetime','default' => null]);
            $this->addColumn($toSchema, $tableName, 'created_by_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
            $this->addColumn($toSchema, $tableName, 'modified_by_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
            $this->addColumn($toSchema, $tableName, 'extensible_enum_option_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);
            $this->addColumn($toSchema, $tableName, 'classification_attribute_id', ['type' => 'varchar', 'len' => 24, 'default' => null]);

            $toSchema->getTable($tableName)->setPrimaryKey(['id']);
            $toSchema->getTable($tableName)->addUniqueIndex(['deleted','extensible_enum_option_id','classification_attribute_id']);
            $toSchema->getTable($tableName)->addIndex(['created_by_id','deleted'],'IDX_CLASSIFICATION_EXTENSIBLE_ENUM_OPTION_CREATED_BY_ID');
            $toSchema->getTable($tableName)->addIndex(['modified_by_id','deleted'], 'IDX_CLASSIFICATION_EXTENSIBLE_ENUM_OPTION_MODIFIED_BY_ID');
            $toSchema->getTable($tableName)->addIndex(['extensible_enum_option_id','deleted']);
            $toSchema->getTable($tableName)->addIndex(['classification_attribute_id','deleted'],'IDX_CLASSIFICATION_EXTENSIBLE_ENUM_OPTION_CLASSIFICATION_ID');
            $toSchema->getTable($tableName)->addIndex(['created_at','deleted'],'IDX_CLASSIFICATION_EXTENSIBLE_ENUM_OPTION_CREATED_AT');
            $toSchema->getTable($tableName)->addIndex(['modified_at','deleted'],'IDX_CLASSIFICATION_EXTENSIBLE_ENUM_OPTION_MODIFIED_AT');


            foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
                $this->execute($sql);
            }

        }
    }

    public function down(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;
        $toSchema->dropTable('classification_attribute_extensible_enum_option');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->execute($sql);
        }
    }

    /**
     * @param string $sql
     */
    protected function execute(string $sql)
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
