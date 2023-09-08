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

use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Migration\Base;

class V1Dot6Dot14 extends Base
{
    public function up(): void
    {
        $this->exec("DROP INDEX UNIQ_CCC4BE1F4584665AB6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_attribute_value");
        $this->exec("CREATE UNIQUE INDEX UNIQ_CCC4BE1FEB3B4E334584665AB6E62EFAD4DB71B5AF55D372F5A1AA ON product_attribute_value (deleted, product_id, attribute_id, language, scope, channel_id)");

        $this->exec("DROP INDEX UNIQ_BD38116AADFEE0E7B6E62EFAAF55D372F5A1AAD4DB71B5EB3B4E33 ON product_family_attribute");
        $this->exec("CREATE UNIQUE INDEX UNIQ_BD38116AEB3B4E33ADFEE0E7B6E62EFAD4DB71B5AF55D372F5A1AA ON product_family_attribute (deleted, product_family_id, attribute_id, language, scope, channel_id)");

        $this->exec("DROP INDEX IDX_NAME ON product_family_attribute");
        $this->exec("ALTER TABLE product_family_attribute DROP name");
    }

    public function down(): void
    {
        throw new BadRequest('Downgrade is prohibited.');
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
