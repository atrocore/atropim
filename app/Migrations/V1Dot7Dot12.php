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

class V1Dot7Dot12 extends Base
{
    public function up(): void
    {
        try {
            $this->getPDO()->exec("ALTER TABLE associated_product ADD sorting INT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        } catch (\Throwable $e) {
            // ignore all
        }

        $limit = 5000;
        $offset = 0;

        while (!empty($ids = $this->getPDO()->query("SELECT id FROM product WHERE deleted=0 LIMIT $limit OFFSET $offset")->fetchAll(\PDO::FETCH_COLUMN))) {
            foreach ($ids as $id) {
                $relationIds = $this->getPDO()->query("SELECT id FROM associated_product WHERE main_product_id='$id' AND deleted=0 ORDER BY sorting")->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($relationIds as $k => $relationId) {
                    $sorting = $k * 10;
                    $this->getPDO()->exec("UPDATE associated_product SET sorting=$sorting WHERE id='$relationId'");
                }
            }
            $offset = $offset + $limit;
        }
    }

    public function down(): void
    {
        $this->getPDO()->exec("ALTER TABLE associated_product DROP sorting");
    }
}
