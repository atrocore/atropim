<?php

declare(strict_types=1);

namespace Pim\Services;

/**
 * Class AbstractProductDashletService
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
abstract class AbstractProductDashletService extends AbstractDashletService
{
    /**
     * Product types
     *
     * @var array
     */
    protected $productTypes = [];

    /**
     * @inheritdoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    /**
     * Get product types for query
     *
     * @return string
     */
    protected function getProductTypesCondition(): string
    {
        $result = "('" . implode("','", $this->getProductTypes()) . "')";

        return $result;
    }


    /**
     * Get product types
     *
     * @return array
     */
    protected function getProductTypes(): array
    {
        if (empty($this->productTypes)) {
            $this->productTypes = array_keys($this->getInjection('metadata')->get('pim.productType'));
        }

        return $this->productTypes;
    }
}
