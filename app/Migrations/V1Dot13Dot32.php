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

class V1Dot13Dot32 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-09-17 08:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("CREATE INDEX IDX_ASSOCIATED_PRODUCT_CREATED_AT ON associated_product (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_ASSOCIATED_PRODUCT_MODIFIED_AT ON associated_product (modified_at, deleted);");
            $this->exec("ALTER INDEX idx_associated_product_unique_relationship RENAME TO IDX_ASSOCIATED_PRODUCT_UNIQUE_RELATION");
        } else {
            $this->exec("CREATE INDEX IDX_ASSOCIATED_PRODUCT_CREATED_AT ON associated_product (created_at, deleted);");
            $this->exec("CREATE INDEX IDX_ASSOCIATED_PRODUCT_MODIFIED_AT ON associated_product (modified_at, deleted);");
            $this->exec("ALTER TABLE associated_product RENAME INDEX idx_associated_product_unique_relationship TO IDX_ASSOCIATED_PRODUCT_UNIQUE_RELATION");
        }
    }

    public function down(): void
    {
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
