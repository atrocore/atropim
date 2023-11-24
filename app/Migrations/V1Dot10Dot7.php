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
            ->select('id', 'category_parent_id', 'sort_order')
            ->from('category')
            ->where('category_parent_id is not null')
            ->fetchAllAssociative();

        foreach ($rows as $row) {
            $connection->createQueryBuilder()
                ->insert('category_hierarchy')
                ->setValue('entity_id', ':id')
                ->setValue('parent_id', ':category_parent_id')
                ->setValue('hierarchy_sort_order', ':sort_order')
                ->setParameter('id', $row['id'])
                ->setParameter('category_parent_id', $row['category_parent_id'])
                ->setParameter('sort_order', $row['sort_order'])
                ->executeQuery();
        }


        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'category', 'category_parent_id');

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
