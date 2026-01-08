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

class V1Dot15Dot21 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-01-08 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION");
            $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION ON product_file (deleted, file_id, product_id)");
        } else {
            $this->exec("DROP INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION ON product_file");
            $this->exec("CREATE UNIQUE INDEX IDX_PRODUCT_FILE_UNIQUE_RELATION ON product_file (deleted, file_id, product_id)");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
