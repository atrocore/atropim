<?php

namespace Pim\Controllers;

use Espo\Core\Templates\Controllers\Base;
use Espo\Core\Exceptions;
use Slim\Http\Request;

/**
 * AbstractController controller
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
abstract class AbstractController extends Base
{

    /**
     * Validate Get action
     *
     * @param Request $request
     * @param array   $params
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isReadAction(Request $request, array $params = []): bool
    {
        // is get?
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        // is granted?
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Validate Put action
     *
     * @param Request $request
     * @param string  $entityId
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isEditAction(Request $request, string $entityId): bool
    {
        // is put or isset entityId ?
        if ((!$request->isPut() && !$request->isPatch()) || empty($entityId)) {
            throw new Exceptions\BadRequest();
        }
        // is granted?
        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Validate Post action
     *
     * @param Request $request
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isCreateAction(Request $request)
    {
        // is post?
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // is granted?
        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Validate delete action
     *
     * @param Request $request
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isDeleteAction(Request $request)
    {
        // is delete?
        if (!$request->isDelete()) {
            throw new Exceptions\BadRequest();
        }

        // is granted?
        if (!$this->getAcl()->check($this->name, 'delete')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Validate mass update action
     *
     * @param Request $request
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isMassUpdateAction(Request $request)
    {
        // is put?
        if (!$request->isPut()) {
            throw new Exceptions\BadRequest();
        }

        // is granted?
        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Validate mass delete action
     *
     * @param Request $request
     *
     * @return bool
     * @throws Exceptions\BadRequest
     * @throws Exceptions\Forbidden
     */
    public function isMassDeleteAction(Request $request)
    {
        // is post?
        if (!$request->isPost()) {
            throw new Exceptions\BadRequest();
        }

        // is granted?
        if (!$this->getAcl()->check($this->name, 'delete')) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }

    /**
     * Get action request
     *
     * @param array $params
     * @param array $data
     * @param Request $request
     * @return array
     * @throws Exceptions\Error
     */
    public function actionGetRequest($params, $data, Request $request)
    {
        if ($this->isAction($params) && $this->isReadAction($request, $params)) {
            $method = 'get'.ucfirst($params['name']);
            if (method_exists($this, $method)) {
                return $this->{$method}($params['entity_id']);
            }
        }

        throw new Exceptions\Error();
    }

    /**
     * Update action request
     *
     * @param array $params
     * @param array $data
     * @param Request $request
     * @return array
     * @throws Exceptions\Error
     */
    public function actionUpdateRequest($params, $data, Request $request)
    {
        if ($this->isAction($params) && $this->isEditAction($request, $params['entity_id'])) {
            $method = 'update'.ucfirst($params['name']);
            if (method_exists($this, $method)) {
                return $this->{$method}($params['entity_id'], $data);
            }
        }

        throw new Exceptions\Error();
    }

    /**
     * Check for editing a particular object
     *
     * @param string $entityName
     * @param string $entityId
     *
     * @return bool
     * @throws Exceptions\Forbidden
     */
    public function isEditEntity(string $entityName, string $entityId): bool
    {
        $entity = $this->getEntityManager()->getEntity($entityName, $entityId);

        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Exceptions\Forbidden();
        }
        return true;
    }

    /**
     * Checking acl for reading of a particular object
     *
     * @param string $entityName
     * @param string $entityId
     *
     * @return bool
     * @throws Exceptions\Forbidden
     */
    public function isReadEntity(string $entityName, string $entityId): bool
    {
        $entity = $this->getEntityManager()->getEntity($entityName, $entityId);

        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Exceptions\Forbidden();
        }
        return true;
    }

    /**
     * Is action?
     *
     * @param array $params
     *
     * @return bool
     */
    protected function isAction(array $params): bool
    {
        // prepare result
        $result = false;

        if (isset($params['name'])) {
            $result = true;
        }

        return $result;
    }
}
