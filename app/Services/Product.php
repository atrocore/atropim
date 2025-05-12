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

use Atro\Core\EventManager\Event;
use Atro\Core\Templates\Repositories\Base;
use Atro\Entities\File;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Conflict;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Hierarchy;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Atro\Services\MassActions;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;

class Product extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['data'];
    protected $noEditAccessRequiredLinkList = ['categories'];

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        // set main images
        if (count($collection) > 0) {
            $conn = $this->getEntityManager()->getConnection();

            $res = $conn->createQueryBuilder()
                ->select('ps.id, a.id as file_id, a.name, ps.product_id')
                ->from('product_file', 'ps')
                ->innerJoin('ps', 'file', 'a', 'a.id=ps.file_id AND a.deleted=:false')
                ->where('ps.product_id IN (:productsIds)')
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
                    if ($item['product_id'] === $entity->get('id')) {
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
                ->getRepository('ProductFile')
                ->where([
                    'productId'   => $entity->get('id'),
                    'isMainImage' => true
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

    /**
     * @param \stdClass $data
     *
     * @return array
     * @throws BadRequest
     */
    public function addAssociateProducts(\stdClass $data): array
    {
        // input data validation
        if (!property_exists($data, 'foreignWhere') || !is_array($data->foreignWhere)
            || !property_exists($data, 'associationId')
            || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        /** @var Entity $association */
        $association = $this->getEntityManager()->getEntity("Association", $data->associationId);
        if (empty($association)) {
            throw new BadRequest($this->exception('noSuchAssociation'));
        }

        $selectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->where), true)], true);

        $products = $this->getRepository()->select(['id'])->find($selectParams);
        $ids = array_column($products->toArray(), 'id');

        $foreignSelectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->foreignWhere), true)], true);

        $foreignProducts = $this->getRepository()->select(['id'])->find($foreignSelectParams);
        $foreignIds = array_column($foreignProducts->toArray(), 'id');

        /**
         * Collect entities for saving
         */
        $toSave = [];
        foreach ($ids as $mainProductId) {
            foreach ($foreignIds as $relatedProductId) {
                $attachment = new \stdClass();
                $attachment->associationId = $data->associationId;
                $attachment->mainProductId = $mainProductId;
                $attachment->relatedProductId = $relatedProductId;
                if (!empty($backwardAssociationId = $association->get('backwardAssociationId'))) {
                    $attachment->backwardAssociationId = $backwardAssociationId;
                }

                $toSave[] = $attachment;
            }
        }

        $associatedProductService = $this->getServiceFactory()->create('AssociatedProduct');

        $error = [];
        foreach ($toSave as $attachment) {
            try {
                $entity = $associatedProductService->createEntity($attachment);
            } catch (BadRequest $e) {
                $error[] = [
                    'id'          => $entity->get('mainProductId'),
                    'name'        => $this->getEntityManager()->getEntity('Product', $entity->get('mainProductId'))->get('name'),
                    'foreignId'   => $entity->get('relatedProductId'),
                    'foreignName' => $this->getEntityManager()->getEntity('Product', $entity->get('relatedProductId'))->get('name'),
                    'message'     => utf8_encode($e->getMessage())
                ];
            }
        }

        return ['message' => $this->getMassActionsService()->createRelationMessage(count($toSave) - count($error), $error, 'Product', 'Product')];
    }

    /**
     * Remove product association
     *
     * @param \stdClass $data
     *
     * @return array|bool
     * @throws BadRequest
     */
    public function removeAssociateProducts(\stdClass $data): array
    {
        // input data validation
        if (!property_exists($data, 'foreignWhere') || !is_array($data->foreignWhere)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        $selectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->where), true)], true);

        $products = $this->getRepository()->select(['id'])->find($selectParams);
        $ids = array_column($products->toArray(), 'id');

        $foreignSelectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->foreignWhere), true)], true);
        $foreignProducts = $this->getRepository()->select(['id'])->find($foreignSelectParams);
        $foreignIds = array_column($foreignProducts->toArray(), 'id');

        $where = [
            'mainProductId'    => $ids,
            'relatedProductId' => $foreignIds
        ];

        if (property_exists($data, 'associationId') && !empty($data->associationId)) {
            $where['associationId'] = $data->associationId;
        }

        $associatedProducts = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->where($where)
            ->find();

        $success = 0;
        $error = [];
        foreach ($associatedProducts as $associatedProduct) {
            try {
                $this->getEntityManager()->removeEntity($associatedProduct);
                $success++;
            } catch (BadRequest $e) {
                $error[] = [
                    'id'          => $associatedProduct->get('mainProductId'),
                    'name'        => $associatedProduct->get('mainProduct')->get('name'),
                    'foreignId'   => $associatedProduct->get('relatedProductId'),
                    'foreignName' => $associatedProduct->get('relatedProduct')->get('name'),
                    'message'     => utf8_encode($e->getMessage())
                ];
            }
        }

        return ['message' => $this->getMassActionsService()->createRelationMessage($success, $error, 'Product', 'Product', false)];
    }

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateAssociatedMainProducts(Entity $product, Entity $duplicatingProduct)
    {
        // get data
        $data = $duplicatingProduct->get('associatedMainProducts');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['mainProductId'] = $product->get('id');
                $item['backwardAssociatedProductId'] = null;

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateAssociatedRelatedProduct(Entity $product, Entity $duplicatingProduct)
    {
        // get data
        $data = $duplicatingProduct->get('associatedRelatedProduct');

        // copy
        if (count($data) > 0) {
            foreach ($data as $row) {
                $item = $row->toArray();
                $item['id'] = Util::generateId();
                $item['relatedProductId'] = $product->get('id');
                $item['backwardAssociatedProductId'] = null;

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
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
            ->getRepository('ProductFile')
            ->where(['productId' => $duplicatingProduct->get('id')])
            ->find();

        foreach ($productFiles as $productFile) {
            $item = $productFile->toArray();
            $item['id'] = Util::generateId();
            $item['productId'] = $product->get('id');

            $entity = $this->getEntityManager()->getEntity('ProductFile');
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
            'productId' => $entity->get('id'),
            'fileId'    => $file->get('id')
        ];

        $repository = $this->getEntityManager()->getRepository('ProductFile');

        $productFile = $repository->where($where)->findOne();
        if (empty($productFile)) {
            $productFile = $repository->get();
            $productFile->set($where);
        }
        $productFile->set('isMainImage', true);

        $this->getEntityManager()->saveEntity($productFile);
    }

    /**
     * @return MassActions
     */
    protected function getMassActionsService(): MassActions
    {
        return $this->getServiceFactory()->create('MassActions');
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'Product');
    }

    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $post = clone $data;

        $this->setProductMainImage($entity);

        return parent::isEntityUpdated($entity, $post);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }

    protected function getMandatoryLinksToMerge(): array
    {
        $links = parent::getMandatoryLinksToMerge();
        $links[] = 'associatedRelatedProduct';

        return $links;
    }

    protected  function applyMergeForAssociatedRelatedProduct(Entity $target, array $sourceList): void
    {
        $sourceIds = [];

        foreach ($sourceList as $source) {
            $sourceIds[] = $source->get('id');
        }

        /** @var Base $repository */
        $repository = $this->getEntityManager()->getRepository('AssociatedProduct');

        $associatedProducts = $repository
            ->where(['relatedProductId' => $sourceIds])
            ->find();

        foreach ($associatedProducts as $associatedProduct) {
            if($associatedProduct->get('mainProductId') === $target->get('id')){
                $associatedProduct->delete();
                continue;
            }
            $associatedProduct->set('relatedProductId', $target->get('id'));
            $repository->save($associatedProduct);
        }
    }
}
