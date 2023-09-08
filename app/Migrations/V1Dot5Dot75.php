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

class V1Dot5Dot75 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->getPDO()->exec("DELETE FROM product_channel WHERE channel_id IS NULL OR channel_id=''");
        $this->getPDO()->exec("DELETE FROM product_channel WHERE product_id IS NULL OR product_id=''");
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
    }
}
