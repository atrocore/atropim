<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Jobs;

use Espo\Core\Jobs\Base;

/**
 * Class PimCleanup
 */
class PimCleanup extends Base
{
    /**
     * Run job
     *
     * @return bool
     */
    public function run()
    {
        // association
        $ids = $this->fetchIds("SELECT id FROM association WHERE deleted=1");
        $this->execute("UPDATE associated_product SET deleted=1 WHERE association_id IN ('$ids')");
        $this->execute("DELETE FROM association WHERE deleted=1");

        // attribute
        $ids = $this->fetchIds("SELECT id FROM attribute WHERE deleted=1");
        $this->execute("UPDATE product_family_attribute SET deleted=1 WHERE attribute_id IN ('$ids')");
        $this->execute("UPDATE product_attribute_value SET deleted=1 WHERE attribute_id IN ('$ids')");
        $this->execute("DELETE FROM attribute WHERE deleted=1");

        // attribute_group
        $this->execute("DELETE FROM attribute_group WHERE deleted=1");

        // brand
        $this->execute("DELETE FROM brand WHERE deleted=1");

        // catalog
        $this->execute("DELETE FROM catalog WHERE deleted=1");

        // category
        $ids = $this->fetchIds("SELECT id FROM category WHERE deleted=1");
        $this->execute("UPDATE product_category SET deleted=1 WHERE category_id IN ('$ids')");
        $this->execute("DELETE FROM category WHERE deleted=1");

        // channel
        $this->execute("DELETE FROM channel WHERE deleted=1");

        // measuring_unit
        $this->execute("DELETE FROM measuring_unit WHERE deleted=1");

        // packaging
        $this->execute("DELETE FROM packaging WHERE deleted=1");

        // product
        $ids = $this->fetchIds("SELECT id FROM product WHERE deleted=1");
        $this->execute("UPDATE product_attribute_value SET deleted=1 WHERE product_id IN ('$ids')");
        $this->execute("UPDATE product_category SET deleted=1 WHERE product_id IN ('$ids')");
        $this->execute("UPDATE associated_product SET deleted=1 WHERE main_product_id IN ('$ids')");
        $this->execute("UPDATE associated_product SET deleted=1 WHERE related_product_id IN ('$ids')");
        $this->execute("DELETE FROM product WHERE deleted=1");

        // product_attribute_value
        $this->execute("DELETE FROM product_attribute_value WHERE deleted=1");

        // product_category
        $this->execute("DELETE FROM product_category WHERE deleted=1");

        // product_family
        $ids = $this->fetchIds("SELECT id FROM product_family WHERE deleted=1");
        $this->execute("UPDATE product_family_attribute SET deleted=1 WHERE product_family_id IN ('$ids')");
        $this->execute("DELETE FROM product_family WHERE deleted=1");

        // product_family_attribute
        $this->execute("DELETE FROM product_family_attribute WHERE deleted=1");

        // associated_product
        $this->execute("DELETE FROM associated_product WHERE deleted=1");

        // product_serie
        $this->execute("DELETE FROM product_serie WHERE deleted=1");

        // tax
        $this->execute("DELETE FROM tax WHERE deleted=1");

        return true;
    }

    /**
     * @param string $sql
     *
     * @return \PDOStatement|null
     */
    protected function execute(string $sql): ?\PDOStatement
    {
        try {
            $statement = $this->getEntityManager()->nativeQuery($sql);
        } catch (\PDOException $e) {
            $GLOBALS['log']->error('PimCleanup: ' . $e->getMessage());
        }

        return (isset($statement)) ? $statement : null;
    }

    /**
     * @param string $sql
     *
     * @return string
     */
    protected function fetchIds(string $sql): string
    {
        // execute
        $statement = $this->execute($sql);

        return (!is_null($statement)) ? implode("','", array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'id')) : 'no-such-id';
    }
}
