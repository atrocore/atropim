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

class V1Dot8Dot10 extends Base
{
    public function up(): void
    {
        $this->exec("ALTER TABLE classification ADD synonyms LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
                $locale = strtolower($v);
                $this->exec("ALTER TABLE classification ADD synonyms_{$locale} LONGTEXT DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT '(DC2Type:jsonArray)'");
            }
        }
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE classification DROP synonyms");
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $v) {
                $locale = strtolower($v);
                $this->exec("ALTER TABLE classification DROP synonyms_{$locale}");
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
