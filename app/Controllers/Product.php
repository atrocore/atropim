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

use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * Product controller
 */
class Product extends AbstractWithMainImageController
{
    public function actionUpdateActiveForChannel(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isPut() || empty($data->channelId) || empty($data->productId) || !property_exists($data, 'isActiveForChannel')) {
            return false;
        }

        return $this->getRecordService()->updateActiveForChannel((string)$data->channelId, (string)$data->productId, !empty($data->isActiveForChannel));
    }

    /**
     * Action add associated products
     *
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return array
     *
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionAddAssociatedProducts(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }
        if (empty($data->ids) || empty($data->foreignIds)) {
            throw new Exceptions\BadRequest();
        }
        if (!$this->getAcl()->check('Product', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->addAssociateProducts($data);
    }

    /**
     * Action remove associated products
     *
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return array
     *
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionRemoveAssociatedProducts(array $params, \stdClass $data, Request $request): array
    {
        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }
        if (empty($data->ids) || empty($data->foreignIds)) {
            throw new Exceptions\BadRequest();
        }
        if (!$this->getAcl()->check('Product', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->removeAssociateProducts($data);
    }

    public function actionListLinked($params, $data, $request)
    {
        if ($params['link'] === 'productAttributeValues') {
            $where = $request->get('where');
            $where[] = [
                'type'  => 'bool',
                'value' => 'onlyTabAttributes',
                'data'  => ['onlyTabAttributes' => $request->get('tabId')],
            ];
            $request->setQuery('where', $where);
        }

        return parent::actionListLinked($params, $data, $request);
    }
}
