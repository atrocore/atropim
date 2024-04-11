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
use Doctrine\DBAL\ParameterType;

class V1Dot13Dot0 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-10 01:00:00');
    }

    public function up(): void
    {
        foreach (['Brand', 'Category', 'Product'] as $v) {
            $path = "custom/Espo/Custom/Resources/layouts/$v/relationships.json";
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                $contents = str_replace('"assets"', '"files"', $contents);
                file_put_contents($path, $contents);
            }
            $path = "custom/Espo/Custom/Resources/metadata/entityDefs/{$v}Asset.json";
            if (file_exists($path)) {
                rename($path, "custom/Espo/Custom/Resources/metadata/entityDefs/{$v}File.json");
            }
        }

        $this->exec("ALTER TABLE attribute ADD file_type_id VARCHAR(24) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ATTRIBUTE_FILE_TYPE_ID ON attribute (file_type_id, deleted)");

        $res = $this->getConnection()->createQueryBuilder()
            ->select('*')
            ->from($this->getConnection()->quoteIdentifier('attribute'))
            ->where('type=:assetType')
            ->andWhere('deleted=:false')
            ->setParameter('assetType', 'asset')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($res as $v) {
            $this->getConnection()->createQueryBuilder()
                ->update($this->getConnection()->quoteIdentifier('attribute'))
                ->set('type', ':fileType')
                ->where('id=:id')
                ->setParameter('fileType', 'file')
                ->setParameter('id', $v['id'])
                ->executeQuery();

            if (!empty($v['asset_type'])) {
                try {
                    $fileType = $this->getConnection()->createQueryBuilder()
                        ->select('*')
                        ->from('file_type')
                        ->where('deleted=:false')
                        ->andWhere('name=:name')
                        ->setParameter('false', false, ParameterType::BOOLEAN)
                        ->setParameter('name', $v['asset_type'])
                        ->fetchAssociative();
                    if (!empty($fileType)) {
                        $this->getConnection()->createQueryBuilder()
                            ->update($this->getConnection()->quoteIdentifier('attribute'))
                            ->set('file_type_id', ':fileTypeId')
                            ->where('id=:id')
                            ->setParameter('fileTypeId', $fileType['id'])
                            ->setParameter('id', $v['id'])
                            ->executeQuery();
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        foreach (['product', 'category', 'brand'] as $name) {
            $column = $name . '_id';
            if ($this->isPgSQL()) {
                $this->exec("DROP INDEX idx_{$name}_asset_asset_id");
                $this->exec("DROP INDEX IDX_" . strtoupper($name) . "_ASSET_UNIQUE_RELATION");
                $this->exec("ALTER TABLE {$name}_asset ADD file_id VARCHAR(24) DEFAULT NULL");
                $this->exec("ALTER TABLE {$name}_asset RENAME TO {$name}_file");
                $this->exec("CREATE INDEX IDX_" . strtoupper($name) . "_FILE_FILE_ID ON {$name}_file (file_id, deleted)");
            } else {
                $this->exec("DROP INDEX IDX_" . strtoupper($name) . "_ASSET_ASSET_ID ON {$name}_asset");
                $this->exec("DROP INDEX IDX_" . strtoupper($name) . "_ASSET_UNIQUE_RELATION ON {$name}_asset");
                $this->exec("ALTER TABLE {$name}_asset ADD file_id VARCHAR(24) DEFAULT NULL");
                $this->exec("RENAME TABLE {$name}_asset TO {$name}_file");
                $this->exec("CREATE INDEX IDX_" . strtoupper($name) . "_FILE_FILE_ID ON {$name}_file (file_id, deleted)");
            }

            if ($name === 'product') {
                $this->exec("CREATE UNIQUE INDEX IDX_" . strtoupper($name) . "_FILE_UNIQUE_RELATION ON {$name}_file (deleted, product_id, file_id, channel_id)");
            } else {
                $this->exec("CREATE UNIQUE INDEX IDX_" . strtoupper($name) . "_FILE_UNIQUE_RELATION ON {$name}_file (deleted, file_id, {$column})");
            }

            $res = $this->getConnection()->createQueryBuilder()
                ->select('t1.*, a.file_id')
                ->from("{$name}_file", 't1')
                ->join('t1', 'asset', 'a', 't1.asset_id=a.id')
                ->where('t1.deleted=:false')
                ->andWhere('a.deleted=:false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($res as $v) {
                if (!empty($v['file_id'])) {
                    $this->getConnection()->createQueryBuilder()
                        ->update("{$name}_file")
                        ->set('file_id', ':fileId')
                        ->where('id=:id')
                        ->setParameter('id', $v['id'])
                        ->setParameter('fileId', $v['file_id'])
                        ->executeQuery();
                }
            }
        }

        foreach (['Brand', 'Category', 'Product'] as $row) {
            // migrate import
            $qb = $this->getConnection()->createQueryBuilder()
                ->select('t1.*')
                ->from('import_configurator_item', 't1')
                ->innerJoin('t1', 'import_feed', 't2', 't2.id=t1.import_feed_id')
                ->where('t2.data LIKE :str')
                ->andWhere('t1.deleted = :false')
                ->andWhere('t2.deleted = :false')
                ->andWhere('t1.name = :name')
                ->setParameter('name', 'assets')
                ->setParameter('str', '%"entity":"' . $row . '"%')
                ->setParameter('false', false, ParameterType::BOOLEAN);
            try {
                foreach ($qb->fetchAllAssociative() as $v) {
                    $this->getConnection()->createQueryBuilder()
                        ->update('import_configurator_item')
                        ->set('name', ':name')
                        ->where('id = :id')
                        ->setParameter('id', $v['id'])
                        ->setParameter('name', 'files')
                        ->executeQuery();
                }
            } catch (\Throwable $e) {
            }

            // migrate export
            $qb = $this->getConnection()->createQueryBuilder()
                ->select('t1.*')
                ->from('export_configurator_item', 't1')
                ->innerJoin('t1', 'export_feed', 't2', 't2.id=t1.export_feed_id')
                ->where('t2.data LIKE :str')
                ->andWhere('t1.deleted = :false')
                ->andWhere('t2.deleted = :false')
                ->setParameter('str', '%"entity":"' . $row . '"%')
                ->setParameter('false', false, ParameterType::BOOLEAN);
            try {
                foreach ($qb->fetchAllAssociative() as $v) {
                    $toUpdate = false;
                    if ($v['name'] === 'assets') {
                        $v['name'] = 'files';
                        $toUpdate = true;
                    }

                    $exportBy = @json_decode((string)$v['export_by'], true);
                    if (is_array($exportBy) && !empty($exportBy) && in_array('url', $exportBy)) {
                        $v['export_by'] = str_replace('"url"', '"downloadUrl"', $v['export_by']);
                        $toUpdate = true;
                    }

                    if ($toUpdate) {
                        $this->getConnection()->createQueryBuilder()
                            ->update('export_configurator_item')
                            ->set('name', ':name')
                            ->set('export_by', ':exportBy')
                            ->where('id = :id')
                            ->setParameter('id', $v['id'])
                            ->setParameter('name', $v['name'])
                            ->setParameter('exportBy', $v['export_by'])
                            ->executeQuery();
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $this->updateComposer('atrocore/pim', '^1.13.0');
    }

    public function down(): void
    {
        throw new \Error('Downgrade is prohibited');
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
