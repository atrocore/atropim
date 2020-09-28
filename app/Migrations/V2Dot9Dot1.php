<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 2.9.1
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot9Dot1 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        $attributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->distinct()
            ->join('attribute')
            ->where([
                'attribute.type' => ['array', 'multiEnum', 'arrayMultiLang', 'multiEnumMultiLang']
            ])
            ->find();

        if (count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                if (!empty($value = $attribute->get('value'))) {
                    $attribute->set('value', json_encode(json_decode($value), JSON_UNESCAPED_UNICODE));

                    $this->getEntityManager()->saveEntity($attribute);
                }
            }
        }
    }
}
