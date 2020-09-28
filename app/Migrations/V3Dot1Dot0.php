<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Utils\Util;

/**
 * Migration class for version 3.1.0
 *
 * @author r.ratsun@gmail.com
 */
class V3Dot1Dot0 extends V3Dot0Dot1
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        $this->channelAttributeValueUp();
        $this->productFamilyAttributesUp();
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
    }

    /**
     * Migrate attribute value up
     */
    protected function channelAttributeValueUp()
    {
        // prepare sql
        $sql = "SELECT cpav.*, pav.product_id, pav.attribute_id
                FROM channel_product_attribute_value AS cpav
                LEFT JOIN product_attribute_value AS pav ON pav.id=cpav.product_attribute_id AND pav.deleted=0
                WHERE cpav.deleted=0
                  AND pav.product_id IN (SELECT id FROM product WHERE deleted=0)
                  AND cpav.channel_id IN (SELECT id FROM channel WHERE deleted=0)";

        // get data
        try {
            $data = $this->fetchAll($sql);
        } catch (\PDOException $e) {
            $data = [];
        }

        if (!empty($data)) {
            // prepare sql
            $sql = '';

            // prepare count
            $count = 0;

            foreach ($data as $row) {
                // prepare data
                $id = Util::generateId();
                $productId = $row['product_id'];
                $attributeId = $row['attribute_id'];
                $createdAt = date('Y-m-d H:i:s');
                $values['value'] = $row['value'];
                if (!empty($this->getConfig()->get('isMultilangActive'))) {
                    foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                        // prepare key
                        $key = 'value_' . strtolower($locale);

                        // push
                        $values[$key] = $row[$key];
                    }
                }
                $valuesKeys = implode(",", array_keys($values));
                $valuesValue = implode("','", $values);
                $channelId = $row['channel_id'];

                // prepare sql
                $sql .= "INSERT INTO product_attribute_value (id,product_id,attribute_id,scope,created_by_id,created_at,$valuesKeys) VALUES ('$id','$productId','$attributeId','Channel','system','$createdAt','$valuesValue');";
                $sql .= "INSERT INTO product_attribute_value_channel (product_attribute_value_id,channel_id) VALUES ('$id','$channelId');";

                // prepare count
                $count++;

                if ($count == 500) {
                    $this->execute($sql);

                    // prepare sql
                    $sql = '';

                    // prepare count
                    $count = 0;
                }
            }

            if (!empty($sql)) {
                $this->execute($sql);
            }
        }
        try {
            $this->execute('DROP TABLE channel_product_attribute_value');
        } catch (\PDOException $e) {
        }
    }

    /**
     * Migrate product family attribute up
     */
    protected function productFamilyAttributesUp(): void
    {
        // prepare sql
        $sql
            = "SELECT pfal.*   
                FROM product_family_attribute_linker AS pfal 
                JOIN product_family AS pf ON pf.id=pfal.product_family_id 
                JOIN attribute AS a ON a.id=pfal.attribute_id 
                WHERE pfal.deleted=0 AND pf.deleted=0 AND a.deleted=0";

        // get data
        try {
            $data = $this->fetchAll($sql);
        } catch (\PDOException $e) {
            $data = [];
        }

        if (!empty($data)) {
            // prepare sql
            $sql = '';

            // prepare count
            $count = 0;

            foreach ($data as $row) {
                // prepare data
                $id = Util::generateId();
                $productFamilyId = $row['product_family_id'];
                $attributeId = $row['attribute_id'];
                $isRequired = (int)$row['is_required'];
                $createdAt = date('Y-m-d H:i:s');

                // prepare sql
                $sql .= "INSERT INTO product_family_attribute (id,product_family_id,attribute_id,is_required,scope,created_by_id,created_at) VALUES ('$id','$productFamilyId','$attributeId',$isRequired,'Global','system','$createdAt');";
                $sql .= "UPDATE product_attribute_value SET product_family_attribute_id='$id', is_required=$isRequired WHERE scope='Global' AND attribute_id='$attributeId' AND product_id IN (SELECT id FROM product WHERE product_family_id='$productFamilyId');";

                // prepare count
                $count++;

                if ($count == 500) {
                    $this->execute($sql);

                    // prepare sql
                    $sql = '';

                    // prepare count
                    $count = 0;
                }
            }

            if (!empty($sql)) {
                $this->execute($sql);
            }
        }

        try {
            $this->execute('DROP TABLE product_family_attribute_linker');
        } catch (\PDOException $e) {
        }

        try {
            $this->execute('ALTER TABLE `product_attribute_value` DROP `product_family_id`');
        } catch (\PDOException $e) {
        }
    }
}
