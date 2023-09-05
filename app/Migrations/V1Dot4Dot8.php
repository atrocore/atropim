<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Migrations;

use Treo\Core\Migration\Base;

/**
 * Migration class for version 1.4.8
 */
class V1Dot4Dot8 extends Base
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        foreach ($this->getLocales() as $locale) {
            $this->exec("ALTER TABLE `attribute_tab` ADD name_" . strtolower($locale) . " VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci");
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        foreach ($this->getLocales() as $locale) {
            $this->exec("ALTER TABLE `attribute_tab` DROP name_" . strtolower($locale));
        }
    }

    /**
     * @return array
     */
    protected function getLocales(): array
    {
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            return $this->getConfig()->get('inputLanguageList', []);
        }

        return [];
    }

    /**
     * @param string $query
     *
     * @return void
     */
    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore all
        }
    }
}
