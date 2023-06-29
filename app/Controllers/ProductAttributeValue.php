<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

declare(strict_types=1);

namespace Pim\Controllers;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Templates\Controllers\Relationship;
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
}
