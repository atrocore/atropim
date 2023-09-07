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

class V1Dot7Dot19 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE attribute ADD description LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`;");

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $locale = strtolower($language);

                $this->exec("ALTER TABLE attribute ADD description_{$locale} LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci`;");
            }
        }
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE attribute DROP description;");

        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                $locale = strtolower($language);

                $this->exec("ALTER TABLE attribute DROP description_{$locale}");
            }
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
