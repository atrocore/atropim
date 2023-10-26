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

namespace Pim\Repositories;

use Atro\ORM\DB\RDB\Mapper;
use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Relationship;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Pim\Core\Exceptions\ClassificationAttributeAlreadyExists;

class ClassificationAttribute extends Relationship
{
    public function getInheritedPavs(string $id): EntityCollection
    {
        $result = new EntityCollection([], 'ProductAttributeValue');

        $classificationAttribute = $this->get($id);
        if (empty($classificationAttribute)) {
            return $result;
        }

        $classification = $this->getEntityManager()->getRepository('Classification')->get($classificationAttribute->get('classificationId'));
        if (empty($classification)) {
            return $result;
        }

        $productsIds = $classification->getLinkMultipleIdList('products');
        if (empty($productsIds)) {
            return $result;
        }

        $where = [
            'productId'   => $productsIds,
            'attributeId' => $classificationAttribute->get('attributeId'),
            'language'    => $classificationAttribute->get('language'),
            'scope'       => $classificationAttribute->get('scope'),
        ];

        if ($classificationAttribute->get('scope') === 'Channel') {
            $where['channelId'] = $classificationAttribute->get('channelId');
        }

        $result = $this
            ->getProductAttributeValueRepository()
            ->where($where)
            ->find();

        return $result;
    }

    public function beforeSave(Entity $entity, array $options = [])
    {
        // for unique index
        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        parent::beforeSave($entity, $options);

        $this->validateClassificationAttribute($entity);
    }

    public function validateClassificationAttribute(Entity $entity): void
    {
        $attribute = $this->getAttributeRepository()->get($entity->get('attributeId'));
        if (empty($attribute)) {
            throw new BadRequest("No Attribute '{$entity->get('attributeId')}' has been found.");
        }

        $this->getAttributeRepository()->validateMinMax($entity);
        $this->getProductAttributeValueRepository()->validateValue($attribute, $entity);
    }

    public function save(Entity $entity, array $options = [])
    {
        try {
            $result = parent::save($entity, $options);
        } catch (\Throwable $e) {
            // if duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                if ($entity->isNew()) {
                    return $this->getDuplicateEntity($entity);
                }
                $attribute = $this->getAttributeRepository()->get($entity->get('attributeId'));
                $attributeName = !empty($attribute) ? $attribute->get('name') : $entity->get('attributeId');

                $channelName = $entity->get('scope');
                if ($channelName === 'Channel') {
                    $channel = $this->getEntityManager()->getRepository('Channel')->get($entity->get('channelId'));
                    $channelName = !empty($channel) ? $channel->get('name') : $entity->get('channelId');
                }

                throw new ClassificationAttributeAlreadyExists(
                    sprintf($this->getInjection('language')->translate('attributeRecordAlreadyExists', 'exceptions'), $attributeName, "'$channelName'")
                );
            }

            throw $e;
        }

        return $result;
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        return $this
            ->where([
                'id!='             => $entity->get('id'),
                'classificationId' => $entity->get('classificationId'),
                'attributeId'      => $entity->get('attributeId'),
                'scope'            => $entity->get('scope'),
                'channelId'        => $entity->get('channelId'),
                'language'         => $entity->get('language'),
                'deleted'          => $deleted,
            ])
            ->findOne(['withDeleted' => $deleted]);
    }

    public function remove(Entity $entity, array $options = [])
    {
        try {
            $result = parent::remove($entity, $options);
        } catch (\Throwable $e) {
            // delete duplicate
            if ($e instanceof \PDOException && strpos($e->getMessage(), '1062') !== false) {
                if (!empty($toDelete = $this->getDuplicateEntity($entity, true))) {
                    $this->deleteFromDb($toDelete->get('id'), true);
                }
                return parent::remove($entity, $options);
            }
            throw $e;
        }

        return $result;
    }

    public function getProductChannelsViaClassificationId(string $classificationId): array
    {
        return $this->getConnection()->createQueryBuilder()
            ->select('p.id')
            ->from($this->getConnection()->quoteIdentifier('product'), 'p')
            ->leftJoin('p', 'product_classification', 'pcl', 'p.id = pcl.product_id AND pcl.deleted = :false')
            ->andWhere('pcl.classification_id = :id')
            ->andWhere('p.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('id', $classificationId)
            ->fetchAllAssociative();
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        $this->addDependency('language');
    }

    /**
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, string $scope = 'ClassificationAttribute'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions');
    }

    protected function getAttributeRepository(): Attribute
    {
        return $this->getEntityManager()->getRepository('Attribute');
    }

    protected function getProductAttributeValueRepository(): ProductAttributeValue
    {
        return $this->getEntityManager()->getRepository('ProductAttributeValue');
    }
}
