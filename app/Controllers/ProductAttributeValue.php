<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Relationship;
use Slim\Http\Request;

class ProductAttributeValue extends Relationship
{
    public function actionGroupsPavs($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getGroupsPavs((string)$request->get('productId'), (string)$request->get('tabId'));
    }

    public function actionInheritPav($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'id')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->inheritPav((string)$data->id);
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete() || empty($params['id'])) {
            throw new BadRequest();
        }

        $service = $this->getRecordService();
        $service->simpleRemove = !property_exists($data, 'hierarchically');
        $service->deleteEntity($params['id']);

        return true;
    }

    /**
     * @param array $params
     * @param \stdClass $data
     * @param Request $request
     *
     * @return bool
     *
     * @throws BadRequest
     * @throws Forbidden
     */
    public function actionUnlinkAttributeGroup(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }
        if (!property_exists($data, 'attributeGroupId') || !property_exists($data, 'productId')) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check('ClassificationAttribute', 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->unlinkAttributeGroup($data->attributeGroupId, $data->productId, property_exists($data, 'hierarchically'));
    }

    public function actionSelectAttribute(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if ((!property_exists($data, 'ids') && !property_exists($data, 'where')) || !property_exists($data, 'productId')) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check('ClassificationAttribute', 'edit')) {
            throw new Forbidden();
        }

        $sParams = [];
        if (property_exists($data, 'ids')) {
            $sParams['ids'] = json_decode(json_encode($data->ids), true);
        } else {
            $sParams['where'] = json_decode(json_encode($data->where), true);
        }

        return $this->getRecordService()->linkAttribute($sParams, $data->productId);
    }

    public function actionSelectAttributeGroup(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if ((!property_exists($data, 'ids') && !property_exists($data, 'where')) || !property_exists($data, 'productId') || !property_exists($data, 'attributeWhere')) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check('ClassificationAttribute', 'edit')) {
            throw new Forbidden();
        }

        $sParams = [
            'attributeWhere' => json_decode(json_encode($data->attributeWhere), true)
        ];
        if (property_exists($data, 'ids')) {
            $sParams['ids'] = json_decode(json_encode($data->ids), true);
        } else {
            $sParams['where'] = json_decode(json_encode($data->where), true);
        }

        return $this->getRecordService()->linkAttributeGroup($sParams, $data->productId);
    }
}
