<?php
declare(strict_types=1);

namespace Pim\Entities;

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Espo\Core\Templates\Entities\Base;
use Espo\ORM\EntityCollection;

/**
 * Product entity
 *
 * @author r.ratsun@gmail.com
 */
class Product extends Base
{
    /**
     * @var array
     */
    public $productAttribute = [];

    /**
     * @var string
     */
    protected $entityType = "Product";

    /**
     * @var string
     */
    private $attrMask = "/^attr_(.*)$/";

    /**
     * @inheritdoc
     */
    public function set($p1, $p2 = null)
    {
        // call parent
        parent::set($p1, $p2);

        // for product attribute
        if (is_string($p1) && preg_match_all($this->attrMask, $p1, $parts)) {
            // parse key
            $keyParts = explode("_", $parts[1][0]);

            // prepare data
            $attributeId = (string)$keyParts[0];
            $locale = $this->getLocale(substr($attributeId, -4));
            if (!empty($locale)) {
                $attributeId = substr($attributeId, 0, -4);
            }
            $value = (is_array($p2)) ? json_encode($p2, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : (string)$p2;

            $this->setProductAttributeValue($attributeId, $value, $locale);
        }
    }

    /**
     * @inheritdoc
     */
    public function get($name, $params = [])
    {
        // for product attribute
        if (preg_match_all($this->attrMask, (string)$name, $parts)) {
            // parse key
            $keyParts = explode("_", $parts[1][0]);

            // prepare data
            $attributeId = (string)$keyParts[0];
            $locale = $this->getLocale(substr($attributeId, -4));
            if (!empty($locale)) {
                $attributeId = substr($attributeId, 0, -4);
            }

            return $this->getProductAttributeValue($attributeId, $locale);
        }

        return parent::get($name, $params);
    }

    /**
     * Set product attribute value
     *
     * @param string      $attributeId
     * @param string      $value
     * @param string|null $locale
     *
     * @return Product
     */
    public function setProductAttributeValue(string $attributeId, string $value, string $locale = null): Product
    {
        if (!isset($this->productAttribute[$attributeId])) {
            $this->productAttribute[$attributeId] = [];
        }

        // prepare locale
        if (empty($locale)) {
            $locale = 'default';
        }

        $this->productAttribute[$attributeId]['locales'][$locale] = $value;

        return $this;
    }

    /**
     * Set product attribute data
     *
     * @param string $attributeId
     * @param string $field
     * @param string $data
     *
     * @return Product
     */
    public function setProductAttributeData(string $attributeId, string $field, string $data): Product
    {
        if (!isset($this->productAttribute[$attributeId])) {
            $this->productAttribute[$attributeId] = [];
        }

        $this->productAttribute[$attributeId]['data'][$field] = $data;

        return $this;
    }

    /**
     * Get product attribute value
     *
     * @param string      $attributeId
     * @param string|null $locale
     *
     * @return mixed
     * @throws Error
     */
    public function getProductAttributeValue(string $attributeId, string $locale = null)
    {
        // find
        $attribute = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(
                [
                    'productId'   => $this->get('id'),
                    'attributeId' => $attributeId,
                    'scope'       => 'Global'
                ]
            )
            ->findOne();

        // prepare value
        $value = null;

        if (!empty($attribute)) {
            // prepare key
            $key = 'value';
            if (!empty($locale)) {
                $key .= Util::toCamelCase(strtolower($locale), '_', true);
            }

            // global value
            $value = $attribute->get($key);
        }

        return $value;
    }

    /**
     * Get product attribute data
     *
     * @param string $attributeId
     * @param string $field
     *
     * @return mixed
     *
     * @throws Error
     */
    public function getProductAttributeData(string $attributeId, string $field)
    {
        $value = null;

        // find
        $attribute = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(
                [
                    'productId'   => $this->get('id'),
                    'attributeId' => $attributeId,
                    'scope'       => 'Global'
                ]
            )
            ->findOne();

        if (!empty($attribute) && $attribute->hasField($field)) {
            $value = $attribute->get($field);
        }

        return $value;
    }

    /**
     * Get product categories
     *
     * @return EntityCollection
     * @throws Error
     */
    public function getCategories(): EntityCollection
    {
        if (empty($this->get('id'))) {
            throw new Error('No such Product');
        }

        return $this
            ->getEntityManager()
            ->getRepository('Category')
            ->distinct()
            ->join('productCategories')
            ->where(['productCategories.productId' => $this->get('id')])
            ->find();
    }

    /**
     * @param string $locale
     *
     * @return null|string
     */
    protected function getLocale(string $locale): ?string
    {
        // prepare locale
        $locale = Util::toUnderScore($locale);

        // get input languages list
        $inputLanguageList = $this
            ->getEntityManager()
            ->getRepository($this->getEntityType())
            ->getInputLanguageList();

        foreach ($inputLanguageList as $item) {
            if (strtolower($item) == $locale) {
                return $item;
            }
        }

        return null;
    }

}
