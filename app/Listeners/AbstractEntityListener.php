<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\ServiceFactory;
use Espo\Listeners\AbstractListener;
use Espo\ORM\Entity;
use Pim\Repositories\AbstractRepository;

/**
 * Class AbstractListener
 */
abstract class AbstractEntityListener extends AbstractListener
{
    /**
     * Create service
     *
     * @param string $serviceName
     *
     * @return mixed
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function createService(string $serviceName)
    {
        return $this->getServiceFactory()->create($serviceName);
    }

    /**
     * Entity field is unique?
     *
     * @param Entity $entity
     * @param string $field
     *
     * @return bool
     */
    protected function isUnique(Entity $entity, string $field): bool
    {
        // prepare result
        $result = true;

        // find
        $fundedEntity = $this->getEntityManager()
            ->getRepository($entity->getEntityName())
            ->where([$field => $entity->get($field)])
            ->findOne();

        if (!empty($fundedEntity) && $fundedEntity->get('id') != $entity->get('id')) {
            $result = false;
        }

        return $result;
    }

    /**
     * Get service factory
     *
     * @return ServiceFactory
     */
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    /**
     * Translate
     *
     * @param string $key
     *
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, $scope = ''): string
    {
        return $this->getContainer()->get('language')->translate($key, $label, $scope);
    }
}
