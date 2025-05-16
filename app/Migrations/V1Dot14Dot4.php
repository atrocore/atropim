<?php
/*
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

class V1Dot14Dot4 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-16 10:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE attribute_tab RENAME TO attribute_panel");
        $this->exec("ALTER TABLE attribute RENAME COLUMN attribute_tab_id TO attribute_panel_id");

        if ($this->isPgSQL()) {
            $this->exec("ALTER INDEX idx_attribute_attribute_tab_id RENAME TO IDX_ATTRIBUTE_ATTRIBUTE_PANEL_ID");
            $this->exec("ALTER INDEX idx_attribute_tab_created_by_id RENAME TO IDX_ATTRIBUTE_PANEL_CREATED_BY_ID");
            $this->exec("ALTER INDEX idx_attribute_tab_modified_by_id RENAME TO IDX_ATTRIBUTE_PANEL_MODIFIED_BY_ID");
        } else {
            $this->exec("ALTER TABLE attribute RENAME INDEX idx_attribute_attribute_tab_id TO IDX_ATTRIBUTE_ATTRIBUTE_PANEL_ID");
            $this->exec("ALTER TABLE attribute_panel RENAME INDEX idx_attribute_tab_created_by_id TO IDX_ATTRIBUTE_PANEL_CREATED_BY_ID");
            $this->exec("ALTER TABLE attribute_panel RENAME INDEX idx_attribute_tab_modified_by_id TO IDX_ATTRIBUTE_PANEL_MODIFIED_BY_ID");
        }
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
