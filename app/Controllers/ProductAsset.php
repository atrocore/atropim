<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\Controllers;

use Espo\Core\Exceptions\BadRequest;

class ProductAsset extends \Espo\Core\Templates\Controllers\Relationship
{
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
}
