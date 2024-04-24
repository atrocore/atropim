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

class V1Dot13Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-04-24 15:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("ALTER TABLE product_attribute_value ALTER bool_value DROP DEFAULT;");
            $this->exec("ALTER TABLE classification_attribute ALTER bool_value DROP DEFAULT;");
        } else {
            $this->exec("ALTER TABLE product_attribute_value CHANGE bool_value bool_value TINYINT(1) DEFAULT NULL;");
            $this->exec("ALTER TABLE classification_attribute CHANGE bool_value bool_value TINYINT(1) DEFAULT NULL;");
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
