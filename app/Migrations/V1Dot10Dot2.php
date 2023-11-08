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
use Espo\Core\Exceptions\Error;

class V1Dot10Dot2 extends Base
{
    public function up(): void
    {
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'product_attribute_value', 'owner_user_id');
        $this->dropColumn($toSchema, 'product_attribute_value', 'assigned_user_id');

        $this->dropColumn($toSchema, 'product_attribute_value', 'is_inherit_assigned_user');
        $this->dropColumn($toSchema, 'product_attribute_value', 'is_inherit_owner_user');
        $this->dropColumn($toSchema, 'product_attribute_value', 'is_inherit_teams');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }
    }

    public function down(): void
    {
        throw new Error('Downgrade is prohibited!');
    }
}