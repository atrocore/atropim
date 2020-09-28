<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\ORM\Entity;
use Treo\Core\ServiceFactory;
use Treo\Listeners\AbstractListener;

/**
 * Class AbstractListener
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
abstract class AbstractEntityListener extends AbstractListener
{
    /**
     * @var string
     */
    public static $codePattern = '/^[\p{Ll}0-9_]*$/u';

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
     * Is code unique
     *
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isCodeValid(Entity $entity): bool
    {
        if (!$entity->isAttributeChanged('code')) {
            return true;
        }

        if (!empty($entity->get('code')) && preg_match(self::$codePattern, $entity->get('code'))) {
            return $this->isUnique($entity, 'code');
        }

        return true;
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
