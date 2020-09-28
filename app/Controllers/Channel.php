<?php

namespace Pim\Controllers;

use Espo\Core\Exceptions;
use Slim\Http\Request;
use Treo\Core\Utils\Util;

/**
 * Channel controller
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Channel extends AbstractController
{
    /**
     * Get channel product attributes action
     *
     * @ApiDescription(description="Get channel product attributes")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Markets/Channel/{channel_id}/Product/{product_id}/attributes")
     * @ApiParams(name="channel_id", type="string", is_required=1, description="Channel id")
     * @ApiParams(name="product_id", type="string", is_required=1, description="Product id")
     * @ApiReturn(sample="[{
     * 'productAttributeValueId': 'string',
     * 'attributeId': 'string',
     * 'name': 'string',
     * 'type': 'string',
     * 'isRequired': 'bool',
     * 'typeValue': [
     *   'string',
     *   'string',
     *   '...'
     * ],
     * 'typeValueEnUs': [
     *   'string',
     *   'string',
     *   '...'
     * ],
     * 'typeValue other languages ...': [],
     * 'value': 'array|string',
     * 'valueEnUs': 'array|string',
     * 'value other languages ...': 'array|string',
     * 'attributeGroupId': 'string',
     * 'attributeGroupName': 'string',
     * 'attributeGroupOrder': 'int',
     * 'isCustom': 'bool'
     * }]")
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
    public function actionGetChannelProductAttributes($params, $data, Request $request)
    {
        if ($this->isReadAction($request)
            && $this->getAcl()->check('Attribute', 'read')
            && $this->getAcl()->check('Product', 'read')
        ) {
            return $this->getRecordService()->getChannelProductAttributes($params['channel_id'], $params['product_id']);
        }

        throw new Exceptions\Forbidden();
    }

    /**
     * Get products action
     *
     * @ApiDescription(description="Get products in channel")
     * @ApiMethod(type="GET")
     * @ApiRoute(name="/Markets/Channel/{channel_id}/products")
     * @ApiParams(name="channel_id", type="string", is_required=1, description="Channel id")
     * @ApiReturn(sample="[{
     *     'channelProductId': 'string',
     *     'productId': 'string',
     *     'productName': 'string',
     *     'categories': [
     *          'string - categories name'
     *     ],
     *     'isActive': 'bool',
     *     'isEditable': 'bool'
     *},
     *     '...'
     *]")
     *
     * @param string $channelId
     *
     * @return array
     * @throws Exceptions\Forbidden
     */
    public function getProducts(string $channelId): array
    {
        if ($this->isReadEntity($this->name, $channelId)
            && $this->getAcl()->check('Product', 'read')
        ) {
            return $this->getRecordService()->getProducts($channelId);
        }

        throw new Exceptions\Forbidden();
    }

    /**
     * Set IsActiveEntity for link Channel to Product
     *
     * @return array
     * @throws Exceptions\Forbidden
     * @throws Exceptions\BadRequest
     */
    public function actionSetIsActiveEntity($params, $data, Request $request): bool
    {
        if (!$request->isPut() && !empty($data->entityName) && !empty($data->value) && !empty($data->entityId)) {
            throw new Exceptions\BadRequest();
        }
        $channelId = $params['channelId'];
        if (!$this->isReadEntity($this->name, $channelId) && !$this->getAcl()->check('Product', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->setIsActiveEntity($channelId, $data);
    }
}
