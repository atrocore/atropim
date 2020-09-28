<?php

declare(strict_types=1);

namespace Pim\Migrations;

/**
 * Migration class for version 2.0.1
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot0Dot1 extends \Treo\Core\Migration\AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        // migrate channel attributes
        $this->migrateChannelAttributes();
    }

    /**
     * Migrate channel attributes
     */
    protected function migrateChannelAttributes(): void
    {
        if (!empty($attributes = $this->getEntityManager()->getRepository('ProductAttributeValue')->find())) {
            // prepare sql
            $sql = "";
            foreach ($attributes as $item) {
                // prepare data
                $id = $item->get('id');
                $attributeId = $item->get('attributeId');
                $productId = $item->get('productId');

                $sql .= "UPDATE channel_product_attribute_value SET product_attribute_id='$id' WHERE attribute_id='$attributeId' AND product_id='$productId' AND deleted=0;";
            }

            if (!empty($sql)) {
                $sth = $this
                    ->getEntityManager()
                    ->getPDO()
                    ->prepare($sql);
                $sth->execute();
            }
        }
    }
}
