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

class V1Dot15Dot27 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-03-16 12:00:00');
    }

    public function up(): void
    {
        // 1. Rename productStatus → status in layout_list_item rows linked to Product layouts
        try {
            $this->getDbal()->createQueryBuilder()
                ->update('layout_list_item', 'li')
                ->set('name', ':newName')
                ->where('li.name = :oldName')
                ->andWhere('li.deleted = :false')
                ->andWhere(
                    'li.layout_id IN (SELECT l.id FROM layout l WHERE l.entity = :entity AND l.deleted = :false)'
                )
                ->setParameter('newName', 'status')
                ->setParameter('oldName', 'productStatus')
                ->setParameter('entity', 'Product')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeStatement();
        } catch (\Throwable $e) {
        }

        // 2. Rename productStatus → status in layout_row_item rows linked to Product layouts
        try {
            $this->getDbal()->createQueryBuilder()
                ->update('layout_row_item', 'ri')
                ->set('name', ':newName')
                ->where('ri.name = :oldName')
                ->andWhere('ri.deleted = :false')
                ->andWhere(
                    'ri.section_id IN (SELECT s.id FROM layout_section s WHERE s.deleted = :false AND s.layout_id IN (SELECT l.id FROM layout l WHERE l.entity = :entity AND l.deleted = :false))'
                )
                ->setParameter('newName', 'status')
                ->setParameter('oldName', 'productStatus')
                ->setParameter('entity', 'Product')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeStatement();
        } catch (\Throwable $e) {
        }
    }
}
