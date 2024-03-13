<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Migrations;

use Atro\Core\Migration\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot10Dot19 extends Base
{
    public function up(): void
    {
        // Migrate Data
        $connection = $this->getConnection();
        $limit = 2000;
        $offset = 0;

        while (true) {
            $rows = $connection->createQueryBuilder()
                ->select('id', 'data')
                ->from('note')
                ->where('pav_id is not null')
                ->where('deleted = :false')
                ->where('type = :update')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('update', 'Update')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative();

            if (empty($rows)) {
                break;
            }

            $offset = $offset + $limit;

            $ids = [];
            foreach ($rows as $row) {
                try {
                    $data = json_decode($row['data'], true);
                    foreach (['value', 'valueUnitId'] as $field) {
                        if (array_key_exists($field, $data['attributes']['was'])) {
                            if (empty($data['attributes']['was'][$field]) && empty($data['attributes']['became'][$field])) {
                                $ids[] = $row['id'];
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {

                }
            }

            $connection->createQueryBuilder()
                ->update('note')
                ->set('deleted', ':true')
                ->where('id IN (:ids)')
                ->setParameter('ids', $ids, Mapper::getParameterType($ids))
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->executeStatement();
        }


        $this->updateComposer('atrocore/pim', '^1.10.19');
    }

    public function down(): void
    {
        throw new \Error("Downgrade is prohibited.");
    }
}
