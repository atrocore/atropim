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

use Atro\Core\Templates\Controllers\Relation;
use Atro\Core\Exceptions\BadRequest;
use ATro\Core\Exceptions\Forbidden;
use Atro\Core\Templates\Controllers\Base;

class AssociatedProduct extends Relation
{
    public function actionRemoveFromProduct($params, $data, $request)
    {
        if (!$request->isPost() && !property_exists($data, 'mainProductId')) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'delete')) {
            throw new Forbidden();
        }

        $associationId = property_exists($data, 'associationId') ? (string)$data->associationId : '';
        return $this->getRecordService()->removeAssociations((string)$data->mainProductId, $associationId);
    }
}
