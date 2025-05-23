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

use Atro\Core\Templates\Controllers\Base;
use Atro\Core\Exceptions\Error;
use Slim\Http\Request;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class ClassificationAttribute extends Base
{
    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        $service = $this->getRecordService();

        if (property_exists($data, 'attributesIds')) {

            foreach ($data->attributesIds as $attributeId) {
                $input = clone $data;
                $input->attributeId = $attributeId;
                unset($input->attributesIds);
                try {
                    $entity = $service->createEntity($input);
                } catch (\Throwable $e) {
                    $GLOBALS['log']->error($e->getMessage());
                }
            }
        } else {
            $entity = $service->createEntity($data);
        }

        if (!empty($entity)) {
            return $entity->getValueMap();
        }

        throw new Error();
    }

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        $id = $params['id'];

        if (property_exists($data, 'deletePav') && !empty($data->deletePav)) {
            $this->getRecordService()->deleteEntityWithThemAttributeValues($id);
        } else {
            $this->getRecordService()->deleteEntity($id);
        }

        return true;
    }

    public function actionUnlinkAttributeGroupHierarchy(array $params, \stdClass $data, Request $request): bool
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        if (!property_exists($data, 'attributeGroupId') || !property_exists($data, 'classificationId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check('ClassificationAttribute', 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->unlinkAttributeGroupHierarchy($data->attributeGroupId, $data->classificationId);
    }
}
