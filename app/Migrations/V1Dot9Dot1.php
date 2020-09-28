<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 1.9.1
 *
 * @author r.ratsun@gmail.com
 */
class V1Dot9Dot1 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        $sth = $this->getEntityManager()->getPDO()->prepare("SELECT * FROM product_family_attribute");
        $sth->execute();
        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);

        if (!empty($data)) {
            $sql = '';
            foreach ($data as $row) {
                // prepare data
                $isRequired = $row['is_required'];
                $productFamilyId = $row['product_family_id'];
                $attributeId = $row['attribute_id'];
                $isMultiChannel = $row['is_multi_channel'];

                $sql
                    .= "INSERT INTO product_family_attribute_linker
                            (is_required, product_family_id, attribute_id, is_multi_channel) 
                         VALUES 
                            ($isRequired, '$productFamilyId', '$attributeId', $isMultiChannel);";
            }

            $sql .= "DELETE FROM product_family_attribute";

            // execute
            $sth = $this->getEntityManager()->getPDO()->prepare($sql);
            $sth->execute();
        }
    }
}
