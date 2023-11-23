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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Exceptions\Error;

class V1Dot10Dot6 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'attribute', 'default_value', ['type' => 'text', 'default' => null]);

        // Migrate schema
        $tableName = 'category_hierarchy';
        $table = $toSchema->createTable($tableName);
        $this->addColumn($toSchema, $tableName, 'id', ['type' => 'id', 'dbType' => 'int', 'autoincrement' => true]);
        $this->addColumn($toSchema, $tableName, 'deleted', ['type' => 'bool', 'default' => 0]);
        $this->addColumn($toSchema, $tableName, 'entity_id', ['type' => 'varchar', 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'parent_id', ['type' => 'varchar', 'default' => null]);
        $this->addColumn($toSchema, $tableName, 'hierarchy_sort_order', ['type' => 'int', 'default' => null]);

        $table->setPrimaryKey(['id']);
        $indexes = [
            ['entity_id'],
            ['parent_id'],
            ['entity_id', 'parent_id']
        ];
        foreach ($indexes as $index) {
            $table->addIndex($index);
        }

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }


        // Migrate Data
        $connection = $this->getConnection();
        $rows = $connection->createQueryBuilder()
            ->select('id', 'category_parent_id')
            ->from('category')
            ->where('category_parent_id is not null')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $connection->createQueryBuilder()
                ->insert('category_hierarchy')
                ->setValue('entity_id', ':id')
                ->setValue('parent_id', ':category_parent_id')
                ->setParameter('id', $row['id'])
                ->setParameter('category_parent_id', $row['category_parent_id'])
                ->executeQuery();
        }


        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'category', 'category_parent_id');
        $this->dropColumn($toSchema, 'category', 'category_route_name');
        $this->dropColumn($toSchema, 'category', 'category_route');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}
