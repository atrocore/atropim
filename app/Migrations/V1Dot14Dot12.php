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

class V1Dot14Dot12 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-06-16 17:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE role_scope ADD create_attribute_value_action VARCHAR(255) DEFAULT NULL");
        $this->exec("ALTER TABLE role_scope ADD delete_attribute_value_action VARCHAR(255) DEFAULT NULL");

        if ($this->isPgSQL()) {
            $this->exec("CREATE TABLE role_scope_attribute (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', read_action BOOLEAN DEFAULT 'false' NOT NULL, edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_UNIQUE ON role_scope_attribute (deleted, attribute_id, role_scope_id)");
            $this->exec("CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ATTRIBUTE_ID ON role_scope_attribute (attribute_id, deleted)");
            $this->exec("CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ROLE_SCOPE_ID ON role_scope_attribute (role_scope_id, deleted)");
            $this->exec("CREATE TABLE role_scope_attribute_panel (id VARCHAR(36) NOT NULL, deleted BOOLEAN DEFAULT 'false', read_action BOOLEAN DEFAULT 'false' NOT NULL, edit_action BOOLEAN DEFAULT 'false' NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, attribute_panel_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, PRIMARY KEY(id))");
            $this->exec("CREATE UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_UNIQUE ON role_scope_attribute_panel (deleted, attribute_panel_id, role_scope_id)");
            $this->exec("CREATE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_ROLE_SCOPE_ID ON role_scope_attribute_panel (role_scope_id, deleted)");
        } else {
            $this->exec("CREATE TABLE role_scope_attribute (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', read_action TINYINT(1) DEFAULT '0' NOT NULL, edit_action TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, attribute_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_UNIQUE (deleted, attribute_id, role_scope_id), INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ATTRIBUTE_ID (attribute_id, deleted), INDEX IDX_ROLE_SCOPE_ATTRIBUTE_ROLE_SCOPE_ID (role_scope_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
            $this->exec("CREATE TABLE role_scope_attribute_panel (id VARCHAR(36) NOT NULL, deleted TINYINT(1) DEFAULT '0', read_action TINYINT(1) DEFAULT '0' NOT NULL, edit_action TINYINT(1) DEFAULT '0' NOT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, attribute_panel_id VARCHAR(36) DEFAULT NULL, role_scope_id VARCHAR(36) DEFAULT NULL, created_by_id VARCHAR(36) DEFAULT NULL, modified_by_id VARCHAR(36) DEFAULT NULL, UNIQUE INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_UNIQUE (deleted, attribute_panel_id, role_scope_id), INDEX IDX_ROLE_SCOPE_ATTRIBUTE_PANEL_ROLE_SCOPE_ID (role_scope_id, deleted), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB");
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
