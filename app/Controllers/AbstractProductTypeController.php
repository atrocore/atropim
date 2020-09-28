<?php

declare(strict_types = 1);

namespace Pim\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * AbstractProductType controller
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
abstract class AbstractProductTypeController extends Base
{
    /**
     * Action update
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return array
     */
    public function actionPatch($params, $data, Request $request)
    {
        return $this->actionUpdate($params, $data, $request);
    }

    /**
     * Get action request
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return mixed
     * @throws Exceptions\Error
     * @throws Exceptions\NotFound
     */
    public function actionGetRequest($params, $data, Request $request)
    {
        return $this->process($params, $data, $request);
    }

    /**
     * Update action request
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return mixed
     * @throws Exceptions\Error
     * @throws Exceptions\NotFound
     */
    public function actionUpdateRequest($params, $data, Request $request)
    {
        return $this->process($params, $data, $request);
    }

    /**
     * Delete action request
     *
     * @param array   $params
     * @param array   $data
     * @param Request $request
     *
     * @return mixed
     * @throws Exceptions\NotFound
     */
    public function actionDeleteRequest($params, $data, Request $request)
    {
        return $this->process($params, $data, $request);
    }

    /**
     * Proccess
     *
     * @param type $params
     * @param type $data
     * @param Request $request
     *
     * @return mixed
     * @throws Exceptions\NotFound
     */
    protected function process($params, $data, Request $request)
    {
        // prepare data
        $controller = $params['controller'];
        $action     = 'action'.ucfirst($params['name']);

        if (method_exists($this, $action)) {
            return $this->{$action}($params, $data, $request);
        }

        throw new Exceptions\NotFound("Action '$action' does not exist in controller '$controller'");
    }
}
