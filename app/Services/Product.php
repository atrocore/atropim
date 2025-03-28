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

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        $conflicts = [];

        if (property_exists($data, '_sortedIds') && property_exists($data, '_scope') && $data->_scope == 'Category' && property_exists($data, '_id')) {
            $this->getRepository()->updateSortOrderInCategory($data->_id, $data->_sortedIds);
            return $this->getEntity($id);
        }

        if ($this->isPanelsUpdating($data)) {
            if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->beginTransaction();
                $inTransaction = true;
            }
            $panelsData = json_decode(json_encode($data->panelsData), true);
            foreach ($panelsData as $link => $linkData) {
                if (empty($linkData)) {
                    continue;
                }
                $entityType = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link, 'entity']);

                if (empty($entityType)) {
                    continue;
                }

                $service = $this->getInjection('serviceFactory')->create($entityType);
                $method = 'updatePanelFrom' . $this->entityType;
                if (method_exists($service, $method)) {
                    $conflicts = $service->$method($id, $data);
                }
            }
            $data->_skipIsEntityUpdated = true;
        }

        try {
            $result = parent::updateEntity($id, $data);
        } catch (Conflict $e) {
            $conflicts = array_merge($conflicts, $e->getFields());
        }

        if (!empty($conflicts)) {
            if (!empty($inTransaction)) {
                $this->getEntityManager()->getPDO()->rollBack();
            }
            throw new Conflict(sprintf($this->getInjection('language')->translate('editedByAnotherUser', 'exceptions', 'Global'), implode(', ', $conflicts)));
        }

        if (!empty($inTransaction)) {
            $this->getEntityManager()->getPDO()->commit();
        }

        return $result;
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

    public function getPrismChannelId(): ?string
    {
        $channel = null;
        if (!empty($account = $this->getUser()->get('account')) && !empty($account->get('channelId'))) {
            $channel = $account->get('channel');
        }
        if (empty($channel) && !empty($channelCode = self::getHeader('Channel-Code'))) {
            $channel = $this->getEntityManager()->getRepository('Channel')->where(['code' => $channelCode])->findOne();
        }

        return empty($channel) ? null : $channel->get('id');
    }

    protected function duplicateProductAttributeValues(Entity $product, Entity $duplicatingProduct): void
    {
        $pavs = $duplicatingProduct->get('productAttributeValues');
        if (empty($pavs) || count($pavs) === 0) {
            return;
        }

        /** @var \Pim\Repositories\ProductAttributeValue $repository */
        $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');

        foreach ($pavs as $pav) {
            $entity = $repository->get();
            $entity->set($pav->toArray());
            $entity->id = Util::generateId();
            $entity->set('productId', $product->get('id'));

            try {
                if (!empty($duplicate = $repository->getDuplicateEntity($entity))) {
                    $repository->remove($duplicate);
                }
                $repository->save($entity);
            } catch (ProductAttributeAlreadyExists $e) {
            }
        }
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

    public function createPseudoTransactionCreateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        if (!property_exists($data, 'productId')) {
            return;
        }

        $children = $this->getRepository()->getChildrenArray($data->productId);
        foreach ($children as $child) {
            $inputData = clone $data;
            $inputData->productId = $child['id'];
            $inputData->productName = $child['name'];
            $transactionId = $this->getPseudoTransactionManager()->pushCreateEntityJob('ProductAttributeValue', $inputData, $parentTransactionId);
            if ($child['childrenCount'] > 0) {
                $this->createPseudoTransactionCreateJobs(clone $inputData, $transactionId);
            }
        }
    }

    public function createPseudoTransactionUpdateJobs(\stdClass $data, string $parentTransactionId = null): void
    {
        $children = $this->getRepository()->getChildrenArray($data->productId);
        foreach ($children as $child) {
            $product = $this->getEntity($child['id']);

            if (!empty($product)) {
                $data->productId = $child['id'];

                $transactionId = null;

                foreach ($product->get('productAttributeValues') as $pav) {
                    if ($pav->get('attributeId') == $data->attributeId
                        && $pav->get('channelId') == $data->channelId
                        && $pav->get('language') == $data->language) {
                        $transactionId = $this->getPseudoTransactionManager()->pushUpdateEntityJob('ProductAttributeValue', $pav->id, $data, $parentTransactionId);
                    }
                }

                if ($child['childrenCount'] > 0) {
                    $this->createPseudoTransactionUpdateJobs(clone $data, $transactionId);
                }
            }
        }
    }

    /**
     * @param string $id
     * @param array  $params
     *
     * @return array
     * @throws Forbidden
     * @throws NotFound
     */
    protected function findLinkedEntitiesProductAttributeValues(string $id, array $params): array
    {
        $entity = $this->getEntityManager()->getRepository('Product')->get($id);
        if (!$entity) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($entity, 'read')) {
            throw new Forbidden();
        }

        $foreignEntityName = 'ProductAttributeValue';
        $link = 'productAttributeValues';

        if (!empty($params['maxSize'])) {
            $params['maxSize'] = $params['maxSize'] + 1;
        }

        // get select params
        $selectParams = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);
        if (empty($selectParams['orderBy'])) {
            $selectParams['orderBy'] = 'id';
        }

        // get record service
        $recordService = $this->getRecordService($foreignEntityName);

        /**
         * Prepare select list
         */
        $selectAttributeList = $recordService->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectParams['select'] = array_unique($selectAttributeList);
        }

        $pavs = $this->getEntityManager()->getRepository('Product')->findRelated($entity, $link, $selectParams);
        $collection = new EntityCollection();

        $recordService->prepareCollectionForOutput($pavs);
        foreach ($pavs as $e) {
            if (!$this->getAcl()->check($e, 'read')) {
                continue;
            }

            $recordService->loadAdditionalFieldsForList($e);
            if (!empty($params['loadAdditionalFields'])) {
                $recordService->loadAdditionalFields($e);
            }
            if (!empty($selectAttributeList)) {
                $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
            }
            $recordService->prepareEntityForOutput($e);

            $collection->append($e);
        }

        $collection = $this->preparePavsForOutput($collection);

        $result = [
            'collection' => $collection,
            'total'      => count($collection),
        ];

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))
            ->getArgument('result');
    }

    public function preparePavsForOutput(EntityCollection $collection): EntityCollection
    {
        if (count($collection) === 0) {
            return $collection;
        }

        $scopeData = [];
        if (!empty($collection[0]) && empty($collection[0]->has('channelId'))) {
            $pavsData = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id', 'channelId', 'channelName'])
                ->where(['id' => array_column($collection->toArray(), 'id')])
                ->find();
            foreach ($pavsData as $v) {
                $scopeData[$v->get('id')] = $v;
            }
        } else {
            foreach ($collection as $pav) {
                $scopeData[$pav->get('id')] = $pav;
            }
        }

        $collection = $this->filterPavsViaChannel($collection, $scopeData);

        if (count($collection) === 0) {
            return $collection;
        }

        $records = [];

        foreach ($collection as $pav) {
            if (!isset($scopeData[$pav->get('id')])) {
                continue 1;
            }
            $records[$pav->get('id')] = $pav;
        }

        // clear hided records
        foreach ($collection as $pav) {
            if (!isset($records[$pav->get('id')])) {
                $this->getEntityManager()->getRepository('ProductAttributeValue')->clearRecord($pav->get('id'));
            }
        }

        $headerLanguage = $this->getHeaderLanguage();

        // filtering via header language
        if (!empty($headerLanguage)) {
            foreach ($records as $id => $pav) {
                if ($pav->get('language') !== $headerLanguage) {
                    if ($headerLanguage === 'main') {
                        unset($records[$id]);
                    } else {
                        if ($pav->get('language') !== 'main') {
                            unset($records[$id]);
                        } else {
                            foreach ($records as $pav1) {
                                if (
                                    $pav1->get('language') === $headerLanguage
                                    && $pav1->get('attributeId') == $pav->get('attributeId')
                                    && $pav1->get('channelId') == $pav->get('channelId')
                                ) {
                                    unset($records[$id]);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        foreach ($records as $pav) {
            if (empty($pav->get('channelId'))) {
                $pav->set('channelId', null);
                $pav->set('channelName', 'Global');
            }
        }

        return new EntityCollection(array_values($records));
    }

    protected function filterPavsViaChannel(EntityCollection $collection, array $scopeData): EntityCollection
    {
        if (count($collection) > 0 && !empty($channelId = $this->getPrismChannelId())) {
            $newCollection = new EntityCollection();

            $channelSpecificAttributeIds = [];
            foreach ($collection as $pav) {
                if ($scopeData[$pav->get('id')]->get('channelId') === $channelId) {
                    $channelSpecificAttributeIds[] = $pav->get('attributeId');
                    $newCollection->append($pav);
                }
            }

            foreach ($collection as $pav) {
                if ($scopeData[$pav->get('id')]->get('channelId') === '' && !in_array($pav->get('attributeId'), $channelSpecificAttributeIds)) {
                    $newCollection->append($pav);
                }
            }

            $collection = $newCollection;
        }

        return $collection;
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        parent::beforeCreateEntity($entity, $data);

        if (isset($data->_duplicatingEntityId)) {
            $entity->isDuplicate = true;
        }
    }

    public function createEntity($attachment)
    {
        $entity = parent::createEntity($attachment);

        if (!empty(($parentsIds = $entity->getLinkMultipleIdList('parents'))[0])) {
            foreach ($parentsIds as $parentsId) {
                $this->inheritedAllFromParent($parentsId, $entity);
            }
        }
        return $entity;
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
     * @param array $attributeList
     */
    protected function prepareAttributeListForExport(&$attributeList)
    {
        foreach ($attributeList as $k => $v) {
            if ($v == 'productAttributeValuesIds') {
                $attributeList[$k] = 'productAttributeValues';
            }

            if ($v == 'productAttributeValuesNames') {
                unset($attributeList[$k]);
            }

            if ($v == 'channelsIds') {
                $attributeList[$k] = 'channels';
            }

            if ($v == 'channelsNames') {
                unset($attributeList[$k]);
            }
        }

        $attributeList = array_values($attributeList);
    }

    /**
     * @param Entity $entity
     *
     * @return string|null
     */
    protected function getAttributeProductAttributeValuesFromEntityForExport(Entity $entity): ?string
    {
        if (empty($entity->get('productAttributeValuesIds'))) {
            return null;
        }

        // prepare select
        $select = ['id', 'attributeId', 'attributeName', 'isRequired', 'channelId', 'channelName', 'data', 'value'];
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                $select[] = Util::toCamelCase('value_' . strtolower($locale));
            }
        }

        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select($select)
            ->where(['id' => $entity->get('productAttributeValuesIds')])
            ->find();

        return Json::encode($pavs->toArray());
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

    protected function isPanelsUpdating(\stdClass $data): bool
    {
        if (!property_exists($data, 'panelsData')) {
            return false;
        }

        if (!is_object($data->panelsData)) {
            return false;
        }

        $panelsData = json_decode(json_encode($data->panelsData), true);
        foreach ($panelsData as $link => $linkData) {
            $linkDefs = $this->getMetadata()->get(['entityDefs', $this->entityType, 'links', $link]);
            if (!empty($linkDefs) && !empty($linkData)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $post = clone $data;

        if ($this->isPanelsUpdating($post)) {
            return true;
        }

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

    public function inheritedAllFromParent($parent, $child)
    {
        if (is_string($parent)) {
            $parent = $this->getRepository()->get($parent);
        }

        if (is_string($child)) {
            $child = $this->getRepository()->get($child);
        }

        $pavs = $parent->get('productAttributeValues');
        if (!empty($pavs[0])) {
            $pavRepository = $this->getEntityManager()->getRepository('ProductAttributeValue');
            $pavService = $this->getServiceFactory()->create('ProductAttributeValue');
            foreach ($pavs as $parentPav) {
                $childPav = $pavRepository->getChildPavForProduct($parentPav, $child);

                // create child PAV if not exist
                if (empty($childPav)) {
                    $childPav = $pavRepository->get();
                    $childPav->set($parentPav->toArray());
                    $childPav->id = null;
                    $childPav->set('productId', $child->get('id'));
                    try {
                        $pavRepository->save($childPav);;
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error('Create child PAV failed: ' . $e->getMessage());
                    }
                    continue;
                }

                $pavService->prepareEntityForOutput($childPav);
                if ($childPav->get('isPavValueInherited') === false) {
                    $value = $childPav->get('value');
                    if ($childPav->get('attributeType') === 'file') {
                        $value = $childPav->get('valueId');
                    }

                    if ($value === null) {
                        try {
                            $pavService->inheritPav($childPav);
                        } catch (\Throwable $e) {
                            $GLOBALS['log']->error('Inherit PAV failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    protected function getMandatoryLinksToMerge(): array
    {
        $links = parent::getMandatoryLinksToMerge();
        $links[] = 'associatedRelatedProduct';

        return $links;
    }

    protected  function getForbiddenLinksToMerge(): array {
        $links = parent::getForbiddenLinksToMerge();
        if($this->getConfig()->get('allowSingleClassificationForProduct')){
            $links[] = 'classifications';
        }

        return  $links;
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
