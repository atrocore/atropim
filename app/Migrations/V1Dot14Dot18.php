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

class V1Dot14Dot18 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-07-17 15:00:00');
    }

    public function up(): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update('layout_relationship_item')
            ->set('name',':newName')
            ->where('name=:name')
            ->setParameter('name', 'associatedProducts')
            ->setParameter('newName', 'associatedItems')
            ->executeStatement();
    }
}
