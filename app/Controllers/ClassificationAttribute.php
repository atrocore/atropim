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

use Slim\Http\Request;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class ClassificationAttribute extends AbstractAttributeValueController
{

    public function actionDelete($params, $data, $request)
    {
        if (!$request->isDelete()) {
            throw new BadRequest();
        }

        $id = $params['id'];

        if (property_exists($data, 'deletePav') && !empty($data->deletePav)) {
            $this->getRecordService()->deleteEntityWithThemPavs($id);
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
