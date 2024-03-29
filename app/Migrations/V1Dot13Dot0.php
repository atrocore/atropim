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
        return new \DateTime('2024-03-29');
    }

    public function up(): void
    {
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
                $this->exec("CREATE UNIQUE INDEX IDX_" . strtoupper($name) . "_FILE_UNIQUE_RELATION ON {$name}_file (deleted, product_id, file_id, scope, channel_id)");
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

        // migrate custom layouts

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
