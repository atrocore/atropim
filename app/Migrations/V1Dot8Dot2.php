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

class V1Dot8Dot2 extends Base
{
    public function up(): void
    {
        $this->execute("ALTER TABLE attribute DROP count_bytes_instead_of_characters");
        $this->execute("ALTER TABLE classification_attribute DROP count_bytes_instead_of_characters");
        $this->execute("ALTER TABLE product_attribute_value DROP count_bytes_instead_of_characters");
    
        $this->getPDO()->exec("ALTER TABLE attribute ADD count_bytes_instead_of_characters TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE classification_attribute ADD count_bytes_instead_of_characters TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->getPDO()->exec("ALTER TABLE product_attribute_value ADD count_bytes_instead_of_characters TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE attribute DROP count_bytes_instead_of_characters");
        $this->execute("ALTER TABLE classification_attribute DROP count_bytes_instead_of_characters");
        $this->execute("ALTER TABLE product_attribute_value DROP count_bytes_instead_of_characters");
    }

    protected function execute(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}