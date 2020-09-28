<?php

namespace Pim\Controllers;

use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * Class ProductFamily
 *
 * @author r.ratsun@gmail.com
 */
class ProductFamily extends AbstractController
{
    /**
     * Get count not empty product family attributes
     *
     * @ApiDescription(description="Get products count, linked with product family attribute")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/ProductFamily/{product_family_id}/productAttributesCount")
     * @ApiParams(name="product_family_id", type="string", is_required=1, description="ProductFamily id")
     * @ApiReturn(sample="'int'")
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return int
     *
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionProductsCount($params, $data, Request $request)
    {
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        return $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->join('productFamilyAttribute')
            ->join('product')
            ->where(['productFamilyAttribute.id' => $request->get('attributeId'), 'product.deleted' => 0])
            ->count();
    }
}
