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
        // fetch currencies in the system
        $units = $this->getConnection()->createQueryBuilder()
            ->select(['name', 'id'])
            ->from('unit')
            ->where('measure_id=:id')
            ->setParameter('id', 'currency')
            ->fetchAllKeyValue();

        // add unit column
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->addColumn($toSchema, 'product', 'price_unit_id', ['type' => 'string', 'default' => null]);

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }

        // migrate price data
        $limit = 2000;
        $offset = 0;

        while (true) {

            $products = $this->getConnection()->createQueryBuilder()
                ->from('product')
                ->select(['id', 'price', 'price_currency'])
                ->where('price_currency is not null')
                ->setMaxResults($limit)
                ->setFirstResult($offset)
                ->fetchAllAssociative();

            if (empty($products)) {
                break;
            }

            $offset = $offset + $limit;

            foreach ($products as $product) {
                $unitId = $units[$product['price_currency']] ?? $product['price_currency'];

                $this->getConnection()->createQueryBuilder()
                    ->update('product')
                    ->set('price_unit_id', ':value')
                    ->where('id = :id')
                    ->setParameter('id', $product['id'])
                    ->setParameter('value', $unitId)
                    ->executeStatement();
            }
        }

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

        // remove currency column
        $fromSchema = $this->getCurrentSchema();
        $toSchema = clone $fromSchema;

        $this->dropColumn($toSchema, 'product', 'price_currency');

        foreach ($this->schemasDiffToSql($fromSchema, $toSchema) as $sql) {
            $this->getPDO()->exec($sql);
        }

        // change price field in layouts
        $dir = "custom/Espo/Custom/Resources/layouts/Product";
        $files = scandir($dir);
        foreach ($files as $file) {
            if (in_array($file, array(".", ".."))) {
                continue;
            }
            $path = "$dir/$file";
            if (file_exists($path)) {
                $contents = file_get_contents($path);
                $contents = str_replace('"price"', '"unitPrice"', $contents);
                file_put_contents($path, $contents);
            }
        }

    }

    public function down(): void
    {
        throw new Error("Downgrade is prohibited");
    }
}
