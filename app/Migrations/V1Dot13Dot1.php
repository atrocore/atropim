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

class V1Dot13Dot1 extends Base
{
    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE attribute ADD disable_null_value BOOLEAN DEFAULT 'true' NOT NULL");
            $this->exec("ALTER TABLE product_attribute_value ALTER bool_value DROP NOT NULL");
        } else {
            $this->exec("ALTER TABLE attribute ADD disable_null_value TINYINT(1) DEFAULT '1' NOT NULL;");
            $this->exec("ALTER TABLE product_attribute_value CHANGE bool_value bool_value TINYINT(1) DEFAULT '0';");
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
