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

use Espo\Core\Exceptions;
use Slim\Http\Request;

class Product extends \Atro\Core\Templates\Controllers\Hierarchy
{
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
        if (!property_exists($data, 'where') || !is_array($data->where) || !property_exists($data, 'foreignWhere') || !is_array($data->foreignWhere)) {
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
        if (!property_exists($data, 'where') || !is_array($data->where) || !property_exists($data, 'foreignWhere') || !is_array($data->foreignWhere)) {
            throw new Exceptions\BadRequest();
        }
        if (!$this->getAcl()->check('Product', 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return $this->getRecordService()->removeAssociateProducts($data);
    }
}
