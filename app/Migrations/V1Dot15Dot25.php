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

class V1Dot15Dot25 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-02-05 10:00:00');
    }

    public function up(): void
    {
        if (!$this->getCurrentSchema()->hasTable('report')) {
            return;
        }

        try {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('id', 'group_by', 'order_by')
                ->from('report')
                ->where('entity_name = :entityName and deleted = :false')
                ->setParameter('entityName', 'Product')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($res as $item) {
                $groupBy = json_decode($item['group_by'], true);
                if (empty($groupBy)) {
                    continue;
                }

                if (in_array('productStatus', $groupBy)) {
                    $groupBy[array_search('productStatus', $groupBy)] = 'status';
                } else {
                    continue;
                }

                $orderBy = json_decode($item['order_by'], true);
                if (!empty($orderBy)) {
                    foreach ($orderBy as $k => $value) {
                        if (explode(':', $value)[0] === 'productStatus') {
                            $orderBy[$k] = str_replace('productStatus', 'status', $value);
                        }
                    }
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('report')
                    ->set('group_by', ':groupBy')
                    ->set('order_by', ':orderBy')
                    ->where('id = :id')
                    ->setParameter('groupBy', json_encode($groupBy))
                    ->setParameter('orderBy', json_encode($orderBy))
                    ->setParameter('id', $item['id'])
                    ->executeStatement();
            }
        } catch (\Throwable $e) {
        }
    }
}
