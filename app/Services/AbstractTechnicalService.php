<?php

declare(strict_types=1);


namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\Core\Exceptions;

/**
 * Class AbstractTechService
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class AbstractTechnicalService extends AbstractService
{

    /**
     * Check acl for related Entity when action for technical Entity
     *
     * @param string $entityName
     * @param string $entityId
     * @param string $action
     *
     * @return bool
     * @throws Exceptions\Forbidden
     */
    protected function checkAcl(string $entityName, string $entityId, string $action): bool
    {
        // get entity
        if (!empty($entityId) && !empty($entityName)) {
            $entity = $this
                ->getEntityManager()
                ->getEntity($entityName, $entityId);
        }

        // check Acl
        if (!isset($entity) || !$this->getAcl()->check($entity, $action)) {
            throw new Exceptions\Forbidden();
        }

        return true;
    }


    /**
     * Check is valid data for create
     *
     * @param array $data
     * @param array $requiredParams
     *
     * @return bool
     * @throws Exceptions\BadRequest
     */
    protected function isValidCreateData(array $data, array $requiredParams): bool
    {
        // check data
        foreach ($requiredParams as $field) {
            if (empty($data[$field])) {
                $message = $this->getTranslate('notValid', 'exceptions', 'AbstractTechnical');
                throw new Exceptions\BadRequest($message);
            }
        }

        return true;
    }
}
