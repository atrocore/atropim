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

namespace Pim\Services;

use Atro\Core\Templates\Services\Hierarchy;
use Atro\Core\Utils\Util;
use Atro\Entities\File;
use Doctrine\DBAL\ParameterType;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

class Product extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['routes', 'data'];

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        // set main images
        if (count($collection) > 0) {
            $conn = $this->getEntityManager()->getConnection();
            $idColumn = Util::toUnderScore(lcfirst($this->entityName) . 'Id');
            $res = $conn->createQueryBuilder()
                ->select("ps.id, a.id as file_id, a.name, ps.$idColumn")
                ->from(Util::toUnderScore(lcfirst($this->entityName) . 'File'), 'ps')
                ->innerJoin('ps', 'file', 'a', 'a.id=ps.file_id AND a.deleted=:false')
                ->where("ps.$idColumn IN (:productsIds)")
                ->andWhere('ps.is_main_image = :true')
                ->andWhere('ps.deleted = :false')
                ->setParameter('productsIds', array_column($collection->toArray(), 'id'), $conn::PARAM_STR_ARRAY)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            foreach ($collection as $entity) {
                $entity->set('mainImageId', null);
                $entity->set('mainImageName', null);
                foreach ($res as $item) {
                    if ($item[$idColumn] === $entity->get('id')) {
                        $entity->set('mainImageId', $item['file_id']);
                        $entity->set('mainImageName', $item['name']);
                    }
                }
            }
        }

        parent::loadPreviewForCollection($collection);
    }

    public function prepareEntityForOutput(Entity $entity): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return;
        }
        // set global main image
        $this->setProductMainImage($entity);

        parent::prepareEntityForOutput($entity);
    }

    public function setProductMainImage(Entity $entity): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId')) || $this->isPseudoTransaction()) {
            return;
        }

        if (!$entity->has('mainImageId')) {
            $entity->set('mainImageId', null);
            $entity->set('mainImageName', null);
            $entity->set('mainImagePathsData', null);

            $relEntity = $this
                ->getEntityManager()
                ->getRepository($this->entityName . 'File')
                ->where([
                    lcfirst($this->entityName) . 'Id' => $entity->get('id'),
                    'isMainImage'                     => true
                ])
                ->findOne();

            if (!empty($relEntity) && !empty($relEntity->get('fileId'))) {
                /** @var File $file */
                $file = $this->getEntityManager()->getRepository('File')->get($relEntity->get('fileId'));
                if (!empty($file)) {
                    $entity->set('mainImageId', $file->get('id'));
                    $entity->set('mainImageName', $file->get('name'));
                    $entity->set('mainImagePathsData', $file->getPathsData());
                }
            }
        }
    }

    public function updateEntity($id, $data)
    {
        if (property_exists($data, '_sortedIds') && property_exists($data, '_scope') && $data->_scope == 'Category' && property_exists($data, '_id')) {
            $this->getRepository()->updateSortOrderInCategory($data->_id, $data->_sortedIds);
            return $this->getEntity($id);
        }

        return parent::updateEntity($id, $data);
    }

    public function duplicateProductPrices(Entity $product, Entity $duplicatingProduct): void
    {
        if (!$this->getServiceFactory()->checkExists('ProductPrice')) {
            return;
        }

        try {
            $this->getServiceFactory()->create('ProductPrice')->duplicateProductPrices($product, $duplicatingProduct);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('ProductPrices duplicating failed: ' . $e->getMessage());
        }
    }

    protected function duplicateFiles(Entity $product, Entity $duplicatingProduct)
    {
        $productFiles = $this
            ->getEntityManager()
            ->getRepository($this->entityName . 'File')
            ->where([lcfirst($this->entityName) . 'Id' => $duplicatingProduct->get('id')])
            ->find();

        foreach ($productFiles as $productFile) {
            $item = $productFile->toArray();
            $item['id'] = Util::generateId();
            $item[lcfirst($this->entityName) . 'Id'] = $product->get('id');

            $entity = $this->getEntityManager()->getEntity($this->entityName . 'File');
            $entity->set($item);

            $this->getEntityManager()->saveEntity($entity);
        }
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (isset($data->_duplicatingEntityId)) {
            $entity->isDuplicate = true;
        }
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        parent::afterUpdateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
    }

    protected function saveMainImage(Entity $entity, $data): void
    {
        if (!property_exists($data, 'mainImageId')) {
            return;
        }

        $file = $this->getEntityManager()->getRepository('File')->where(['id' => $data->mainImageId])->findOne();
        if (empty($file)) {
            return;
        }

        $where = [
            lcfirst($this->entityName) . 'Id' => $entity->get('id'),
            'fileId'                          => $file->get('id')
        ];

        $repository = $this->getEntityManager()->getRepository($this->entityName . 'File');

        $productFile = $repository->where($where)->findOne();
        if (empty($productFile)) {
            $productFile = $repository->get();
            $productFile->set($where);
        }
        $productFile->set('isMainImage', true);

        $this->getEntityManager()->saveEntity($productFile);
    }

    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'Product');
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $this->setProductMainImage($entity);

        return parent::isEntityUpdated($entity, $data);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }

    protected function getMandatoryLinksToMerge(): array
    {
        $links = parent::getMandatoryLinksToMerge();
        $links[] = 'associatedRelated' . $this->entityName;

        return $links;
    }

}
