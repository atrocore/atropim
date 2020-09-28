<?php

declare(strict_types=1);

namespace Pim\Controllers;

use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * Product controller
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class Product extends AbstractController
{
    /**
     * Action add associated products
     *
     * @param array     $params
     * @param \stdClass $data
     * @param Request   $request
     *
     * @return bool
     *
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionAddAssociatedProducts(array $params, \stdClass $data, Request $request): bool
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
     * @return bool
     *
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function actionRemoveAssociatedProducts(array $params, \stdClass $data, Request $request): bool
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
}
