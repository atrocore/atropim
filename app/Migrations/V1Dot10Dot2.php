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

namespace Pim\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot2 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX IDX_ASSIGNED_USER ON product_attribute_value");
        $this->exec("DROP INDEX IDX_ASSIGNED_USER_ID ON product_attribute_value");
        $this->exec("DROP INDEX IDX_ASSIGNED_USER_ID_DELETED ON product_attribute_value");
        $this->exec("DROP INDEX IDX_OWNER_USER_ID_DELETED ON product_attribute_value");
        $this->exec("DROP INDEX IDX_OWNER_USER_ID ON product_attribute_value");
        $this->exec("DROP INDEX IDX_OWNER_USER ON product_attribute_value");
        $this->exec("ALTER TABLE product_attribute_value DROP owner_user_id, DROP assigned_user_id");
        $this->exec("ALTER TABLE product_attribute_value DROP is_inherit_assigned_user, DROP is_inherit_owner_user, DROP is_inherit_teams");
    }

    public function down(): void
    {
        $this->exec("ALTER TABLE product_attribute_value ADD is_inherit_assigned_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD is_inherit_owner_user TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD is_inherit_teams TINYINT(1) DEFAULT '0' NOT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("ALTER TABLE product_attribute_value ADD owner_user_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, ADD assigned_user_id VARCHAR(24) DEFAULT NULL COLLATE `utf8mb4_unicode_ci`");
        $this->exec("CREATE INDEX IDX_OWNER_USER_ID ON product_attribute_value (owner_user_id)");
        $this->exec("CREATE INDEX IDX_OWNER_USER_ID_DELETED ON product_attribute_value (owner_user_id, deleted)");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER_ID ON product_attribute_value (assigned_user_id)");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER_ID_DELETED ON product_attribute_value (assigned_user_id, deleted)");
        $this->exec("CREATE INDEX IDX_OWNER_USER ON product_attribute_value (owner_user_id, deleted)");
        $this->exec("CREATE INDEX IDX_ASSIGNED_USER ON product_attribute_value (assigned_user_id, deleted)");
        $this->exec("ALTER TABLE product_attribute_value DROP is_inherit_assigned_user, DROP is_inherit_owner_user, DROP is_inherit_teams");
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}