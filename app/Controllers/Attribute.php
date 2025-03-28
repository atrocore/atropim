<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class Attribute extends Base
{
    public function actionDefaultValue($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getDefaultValue((string)$request->get('id'));
    }

    public function actionRecordAttributes($params, $data, $request)
    {
        if (!$request->isGet() || empty($request->get('entityName')) || empty($request->get('entityId'))) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($request->get('entityName'), 'read')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->getRecordAttributes($request->get('entityName'), $request->get('entityId'));
    }

    public function actionAddAttributeValue($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->entityName) || empty($data->entityId)) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'ids') && !property_exists($data, 'where')){
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->addAttributeValue(
            $data->entityName,
            $data->entityId,
            $data->where,
            $data->ids
        );
    }

    public function actionRemoveAttributeValue($params, $data, $request)
    {
        if (!$request->isPost() || empty($data->entityName) || empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($data->entityName, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->removeAttributeValue($data->entityName, $data->id);
    }
}
