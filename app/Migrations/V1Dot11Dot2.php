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

use Atro\Core\Migration\Base;
use Espo\Core\Exceptions\Error;

class V1Dot11Dot2 extends Base
{
    public function up(): void
    {
        \Atro\Migrations\V1Dot8Dot3::migrateCurrencyField($this, 'Product', 'price', 'currency');

        // fetch currencies in the system
        $units = $this->getConnection()->createQueryBuilder()
            ->select(['name', 'id'])
            ->from('unit')
            ->where('measure_id=:id')
            ->setParameter('id', 'currency')
            ->fetchAllKeyValue();

        // change currency type to float with measure
        $this->getConnection()->createQueryBuilder()
            ->update('attribute')
            ->set('type', ':newType')
            ->set('measure_id', ':measureId')
            ->where('type = :oldType')
            ->setParameter('newType', 'float')
            ->setParameter('oldType', 'currency')
            ->setParameter('measureId', 'currency')
            ->executeStatement();

        // move varchar_value to reference_value, change names with unit ids , change type of pavs
        $limit = 2000;
        $offset = 0;

        while (true) {

            $pavs = $this->getConnection()->createQueryBuilder()
                ->from('product_attribute_value')
                ->select('*')
                ->where('attribute_type = :type')
                ->setParameter('type', 'currency')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative();

            if (empty($pavs)) {
                break;
            }

            $offset = $offset + $limit;

            foreach ($pavs as $pav) {
                $referenceValue = $pav['varchar_value'] ?? $pav['reference_value'];
                if (!empty($referenceValue) && array_key_exists($referenceValue, $units)) {
                    $referenceValue = $units[$referenceValue];
                } else {
                    $referenceValue = null;
                }

                $this->getConnection()->createQueryBuilder()
                    ->update('product_attribute_value')
                    ->set('varchar_value', ':null')
                    ->set('reference_value', ':refValue')
                    ->set('attribute_type', ':type')
                    ->where('id = :id')
                    ->setParameter('id', $pav['id'])
                    ->setParameter('null', null)
                    ->setParameter('refValue', $referenceValue)
                    ->setParameter('type', 'float')
                    ->executeStatement();
            }
        }

        $this->updateComposer('atrocore/pim', '^1.11.2');
    }

    public function down(): void
    {
        throw new Error("Downgrade is prohibited");
    }
}
