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

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

class V1Dot7Dot10 extends Base
{
    public function up(): void
    {
        $limit = 5000;

        foreach (['product', 'category'] as $entity) {
            $offset = 0;
            while (!empty($ids = $this->getPDO()->query("SELECT id FROM $entity WHERE deleted=0 LIMIT $limit OFFSET $offset")->fetchAll(\PDO::FETCH_COLUMN))) {
                foreach ($ids as $id) {
                    $relationIds = $this->getPDO()->query("SELECT id FROM {$entity}_asset WHERE {$entity}_id='$id' AND deleted=0 ORDER BY sorting")->fetchAll(\PDO::FETCH_COLUMN);
                    foreach ($relationIds as $k => $relationId) {
                        $sorting = $k * 10;
                        $this->getPDO()->exec("UPDATE {$entity}_asset SET sorting=$sorting WHERE id='$relationId'");
                    }
                }
                $offset = $offset + $limit;
            }
        }

        $ids = $this->getPDO()->query("SELECT id FROM attribute_group WHERE deleted=0")->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $relationIds = $this->getPDO()->query("SELECT id FROM attribute WHERE attribute_group_id='$id' AND deleted=0 ORDER BY sort_order_in_attribute_group")->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($relationIds as $k => $relationId) {
                $sorting = $k * 10;
                $this->getPDO()->exec("UPDATE attribute SET sort_order_in_attribute_group=$sorting WHERE id='$relationId'");
            }
        }
    }

    public function down(): void
    {
    }
}
