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

class V1Dot7Dot29 extends Base
{
    public function up(): void
    {
        $this->exec("DELETE FROM product_attribute_value pav
                    WHERE pav.deleted = 0 AND pav.scope = 'Channel' AND pav.channel_id NOT IN (
                        SELECT DISTINCT pc.channel_id
                        FROM product_channel pc
                        WHERE pc.product_id = pav.product_id AND pc.deleted = 0
                    )");
    }

    public function down(): void
    {
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}