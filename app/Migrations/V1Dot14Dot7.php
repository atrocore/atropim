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
use Doctrine\DBAL\ParameterType;

class V1Dot14Dot7 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-27 17:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("DROP INDEX uniq_fa7aeffb77153098eb3b4e33");
        } else {
            $this->exec("DROP INDEX UNIQ_FA7AEFFB77153098EB3B4E33 ON attribute");
        }

        $this->exec("CREATE UNIQUE INDEX IDX_ATTRIBUTE_UNIQUE_CODE ON attribute (deleted, entity_id, code)");
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
