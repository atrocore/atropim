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

class V1Dot14Dot1 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-02 12:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE attribute ADD composite_attribute_id VARCHAR(36) DEFAULT NULL");
        $this->exec("CREATE INDEX IDX_ATTRIBUTE_COMPOSITE_ATTRIBUTE_ID ON attribute (composite_attribute_id, deleted)");
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
