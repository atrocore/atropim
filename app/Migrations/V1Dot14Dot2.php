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

class V1Dot14Dot2 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-05-06 11:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE " . $this->getConnection()->quoteIdentifier('attribute') . " ADD entity_id VARCHAR(36) DEFAULT NULL");

        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('attribute'))
            ->set('entity_id', ':entityId')
            ->where('deleted=:false')
            ->setParameter('entityId', 'Product')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->executeQuery();
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
