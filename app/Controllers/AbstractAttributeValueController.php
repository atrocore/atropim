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

namespace Pim\Controllers;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Error;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;


class AbstractAttributeValueController extends Base
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
                $data->attributeId = $attributeId;
                try {
                    $createdEntity = $service->createEntity(clone $data);
                    $entity = $createdEntity;
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

}