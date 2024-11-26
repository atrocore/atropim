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

use Atro\Core\Exceptions\Exception;
use Atro\Core\Migration\Base;

class V1Dot13Dot56 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-11-26 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE channel DROP type");
        $this->exec("ALTER TABLE classification ADD type VARCHAR(255) DEFAULT 'general';");
        $this->exec("DROP TABLE channel_classification;");
    }

    public function down(): void
    {
        throw new Exception("Downgrade prohibited");
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
