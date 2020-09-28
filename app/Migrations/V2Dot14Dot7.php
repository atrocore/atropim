<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;

/**
 * Migration class for version 2.14.7
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot14Dot7 extends \Treo\Core\Migration\AbstractMigration
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
            // prepare value field
            $fields = ['value'];

            // prepare multi lang fields
            if ($this->getConfig()->get('isMultilangActive')) {
                foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                    $fields[] = Util::toCamelCase('value_' . strtolower($locale));
                }
            }

            foreach ($attributes as $attribute) {
                foreach ($fields as $field) {
                    if (!empty($value = $attribute->get($field))) {
                        // update attribute values
                        $attribute->set(
                            $field,
                            Json::encode(Json::decode($value), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
                        );
                    }
                }

                $this->getEntityManager()->saveEntity($attribute);
            }
        }
    }
}
