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

use Atro\Core\Migration\Base;

class V1Dot5Dot97 extends Base
{
    public function up(): void
    {
        $records = $this->getSchema()->getConnection()->createQueryBuilder()
            ->select('id')
            ->from('product_attribute_value')
            ->where('channel_id IS NULL')
            ->fetchAllAssociative();

        foreach ($records as $record) {
            try {
                $this->getPDO()->exec("UPDATE product_attribute_value SET channel_id='' WHERE id='{$record['id']}'");
            } catch (\Throwable $e) {
                $this->getPDO()->exec("DELETE FROM product_attribute_value WHERE id='{$record['id']}'");
            }
        }
    }
}
