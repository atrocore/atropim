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

class V1Dot13Dot86 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-02-26 12:00:00');
    }

    public function up(): void
    {
        $duplicates = $this->getConnection()->createQueryBuilder()
            ->select('product_id', 'file_id')
            ->from('product_file')
            ->groupBy('product_id', 'file_id')
            ->having('count(*) > 1')
            ->fetchAllAssociative();
        foreach ($duplicates as $duplicate) {
            $records = $this->getConnection()->createQueryBuilder()
                ->select('id', 'deleted')
                ->from('product_file')
                ->where('product_id=:productId and file_id=:fileId')
                ->setParameter('productId', $duplicate['product_id'])
                ->setParameter('fileId', $duplicate['file_id'])
                ->fetchAllAssociative();

            $keep = false;

            foreach ($records as $k => $v) {
                if (!$keep && empty($v['deleted'])) {
                    $keep = true;
                    continue;
                }

                $this->getConnection()->createQueryBuilder()
                    ->delete('product_file')
                    ->where('id=:id')
                    ->setParameter('id', $v['id'])
                    ->executeStatement();
            }
        }

        $this->execute("CREATE UNIQUE INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION ON product_file (deleted, product_id, file_id)");
    }


    /**
     * @param string $sql
     */
    protected function execute(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
            // ignore all
        }
    }

}
