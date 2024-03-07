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

namespace Pim\Services;

use Atro\Core\Exceptions\NotModified;
use Atro\Core\EventManager\Event;
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
use Espo\Services\MassActions;
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
                ->select('ps.id, a.file_id, a.name, ps.product_id')
                ->from('product_asset', 'ps')
                ->innerJoin('ps', 'asset', 'a', 'a.id=ps.asset_id AND a.deleted=:false')
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

    public function prepareCollectionForOutput(EntityCollection $collection, array $selectParams = []): void
    {
        parent::prepareCollectionForOutput($collection, $selectParams);

        if (count($collection) === 0) {
            return;
        }

        /**
         * Collect attributes fields
         */
        $attributeFields = [];
        if (array_key_exists('select', $selectParams)) {
            foreach ($selectParams['select'] as $field) {
                $fieldDefs = $this->getMetadata()->get(['entityDefs', 'Product', 'fields', $field]);
                if (!empty($fieldDefs['attributeId']) && !empty($fieldDefs['attributeCode'])) {
                    $attributeFields[] = array_merge($fieldDefs, ['fieldName' => $field]);
                }
            }
        }

        /**
         * Set attributes fields
         */
        $pavs = $this->findProductAttributeValuesForProductsViaAttributes(array_column($attributeFields, 'attributeId'), array_column($collection->toArray(), 'id'));
        if ($pavs !== null) {
            foreach ($collection as $product) {
                $this->setAttributesFieldsForProduct($product, $attributeFields, $pavs);
            }
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        // set global main image
        $this->setProductMainImage($entity);

        if (empty($entity->attributesFieldsIsSet)) {
            /**
             * Collect attributes fields
             */
            $attributeFields = [];
            foreach ($this->getMetadata()->get(['entityDefs', 'Product', 'fields']) as $field => $fieldDefs) {
                if (!empty($fieldDefs['attributeId']) && !empty($fieldDefs['attributeCode'])) {
                    $attributeFields[] = array_merge($fieldDefs, ['fieldName' => $field]);
                }
            }

            /**
             * Set attributes fields
             */
            $pavs = $this->findProductAttributeValuesForProductsViaAttributes(array_column($attributeFields, 'attributeId'), [$entity->get('id')]);
            if ($pavs !== null) {
                $this->setAttributesFieldsForProduct($entity, $attributeFields, $pavs);
            }
        }

        parent::prepareEntityForOutput($entity);
    }

    public function setProductMainImage(Entity $entity): void
    {
        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            return;
        }

        if (!$entity->has('mainImageId')) {
            $entity->set('mainImageId', null);
            $entity->set('mainImageName', null);
            $entity->set('mainImagePathsData', null);

            $productAsset = $this
                ->getEntityManager()
                ->getRepository('ProductAsset')
                ->where([
                    'productId'   => $entity->get('id'),
                    'isMainImage' => true
                ])
                ->findOne();

            if (!empty($productAsset) && !empty($asset = $this->getServiceFactory()->create('Asset')->getEntity($productAsset->get('assetId')))) {
                $entity->set('mainImageId', $asset->get('fileId'));
                $entity->set('mainImageName', $asset->get('fileName'));
                $entity->set('mainImagePathsData', $asset->get('filePathsData'));
            }
        }
    }

    protected function getProductAttributeForUpdating(EntityCollection $pavs, \stdClass $data): ?Entity
    {
        foreach ($pavs as $pav) {
            if ($pav->get('attributeId') == $data->attributeId && $pav->get('language') == $data->language && $pav->get('channelId') == $data->channelId) {
                return $pav;
            }
        }

        return null;
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

        if ($this->isProductAttributeUpdating($data)) {
            if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->beginTransaction();
                $inTransaction = true;
            }

            $pavService = $this->getInjection('serviceFactory')->create('ProductAttributeValue');

            // input data
            $productAttributeValues = new \stdClass();
            // pavs to update
            $pavs = new EntityCollection();

            // For Mass Update
            if (property_exists($data, '_isMassUpdate')) {
                $attributeIds = array_column((array)$data->panelsData->productAttributeValues, 'attributeId');
                $existingPavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['productId' => $id, 'attributeId' => $attributeIds])->find();

                foreach ($data->panelsData->productAttributeValues as $pavData) {
                    $existPav = $this->getProductAttributeForUpdating($existingPavs, $pavData);
                    try {
                        if (!is_null($existPav)) {
                            $productAttributeValues->{$existPav->get('id')} = $pavData;
                            $pavs->append($existPav);
                        } else {
                            $copy = clone $pavData;

                            if (property_exists($copy, 'createPavIfNotExists')) {
                                if (empty($copy->createPavIfNotExists)) {
                                    continue 1;
                                }
                                unset($copy->createPavIfNotExists);
                            }

                            $copy->productId = $id;

                            $result = $pavService->createEntity($copy);

                            if (!$result->get('attributeIsMultilang')) {
                                $existingPavs->append($result);
                            } else {
                                $existingPavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['productId' => $id, 'attributeId' => $attributeIds])->find();
                            }

                        }
                    } catch (Conflict $e) {
                        $conflicts = array_merge($conflicts, $e->getFields());
                    }
                }
            } // For Single Product Update
            else {
                $ids = array_keys((array)$data->panelsData->productAttributeValues);
                $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['id' => $ids])->find();
                $productAttributeValues = $data->panelsData->productAttributeValues;
            }

            foreach ($pavs as $pav) {
                if (!empty($this->getMetadata()->get(['attributes', $pav->get('attributeType'), 'isValueReadOnly']))) {
                    continue;
                }

                // prepare input
                $input = clone $productAttributeValues->{$pav->get('id')};
                if (property_exists($data, '_ignoreConflict') && !empty($data->_ignoreConflict) && property_exists($data, '_prev')) {
                    unset($input->_prev);
                }
                foreach (['attributeId', 'channelId', 'language'] as $field) {
                    if (property_exists($input, $field)) {
                        unset($input->$field);
                    }
                }
                $input->isProductUpdate = true;
                try {
                    $pavService->updateEntity($pav->get('id'), $input);
                } catch (Conflict $e) {
                    $conflicts = array_merge($conflicts, $e->getFields());
                } catch (NotModified $e) {
                    // ignore
                }
            }
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

    protected function handleInput(\stdClass $data, ?string $id = null): void
    {
        if (property_exists($data, 'assetsNames')) {
            unset($data->assetsNames);
        }

        if (property_exists($data, 'assetsIds')) {
            $data->_paAssetsIds = $data->assetsIds;
            unset($data->assetsIds);
        }

        if (property_exists($data, 'assetsAddOnlyMode')) {
            $data->_paAddMode = $data->assetsAddOnlyMode;
            unset($data->assetsAddOnlyMode);
        }

        parent::handleInput($data, $id);
    }

    protected function afterCreateEntity(Entity $entity, $data)
    {
        parent::afterCreateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
        $this->createProductAssets($entity, $data);
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        parent::afterUpdateEntity($entity, $data);

        $this->saveMainImage($entity, $data);
        $this->createProductAssets($entity, $data);
    }

    protected function saveMainImage(Entity $entity, $data): void
    {
        if (!property_exists($data, 'mainImageId')) {
            return;
        }

        $asset = $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $data->mainImageId])->findOne();
        if (empty($asset)) {
            return;
        }

        $where = [
            'productId' => $entity->get('id'),
            'assetId'   => $asset->get('id')
        ];

        $repository = $this->getEntityManager()->getRepository('ProductAsset');

        $productAsset = $repository->where($where)->findOne();
        if (empty($productAsset)) {
            $productAsset = $repository->get();
            $productAsset->set($where);
        }
        $productAsset->set('isMainImage', true);

        $this->getEntityManager()->saveEntity($productAsset);
    }

    /**
     * This needs for old import feeds. For import assets from product
     */
    protected function createProductAssets(Entity $entity, \stdClass $data): void
    {
        if (!property_exists($data, '_paAssetsIds')) {
            return;
        }

        $assets = $this
            ->getEntityManager()
            ->getRepository('Asset')
            ->where(['id' => $data->_paAssetsIds])
            ->find();

        /** @var ProductAsset $service */
        $service = $this->getServiceFactory()->create('ProductAsset');

        foreach ($assets as $asset) {
            $input = new \stdClass();
            $input->productId = $entity->get('id');
            $input->assetId = $asset->get('id');

            try {
                $service->createEntity($input);
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('ProductAsset creating failed: ' . $e->getMessage());
            }
        }

        if (!property_exists($data, '_paAddMode') || empty($data->_paAddMode)) {
            $this
                ->getEntityManager()
                ->getRepository('ProductAsset')
                ->where([
                    'productId' => $entity->get('id'),
                    'assetId!=' => array_column($assets->toArray(), 'id')
                ])
                ->removeCollection();
        }
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

    protected function isProductAttributeUpdating(\stdClass $data): bool
    {
        if (!property_exists($data, 'panelsData')) {
            return false;
        }

        if (!is_object($data->panelsData) || !property_exists($data->panelsData, 'productAttributeValues')) {
            return false;
        }

        return !empty($data->panelsData->productAttributeValues);
    }

    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $post = clone $data;

        if (property_exists($post, '_paAssetsIds')) {
            return true;
        }

        if ($this->isProductAttributeUpdating($post)) {
            return true;
        }

        $this->setProductMainImage($entity);

        return parent::isEntityUpdated($entity, $post);
    }

    protected function getAssets(string $productId): array
    {
        return $this->getInjection('serviceFactory')->create('Asset')->getEntityAssets('Product', $productId);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }

    protected function findProductAttributeValuesForProductsViaAttributes(array $attributesIds, array $productIds): ?EntityCollection
    {
        if (empty($attributesIds) || empty($productIds)) {
            return null;
        }

        $params = [
            'where' => [
                [
                    'type'      => 'in',
                    'attribute' => 'channelId',
                    'value'     => ''
                ],
                [
                    'type'      => 'in',
                    'attribute' => 'attributeId',
                    'value'     => $attributesIds
                ],
                [
                    'type'      => 'in',
                    'attribute' => 'productId',
                    'value'     => $productIds
                ],
            ]
        ];

        $pavs = $this->getServiceFactory()->create('ProductAttributeValue')->findEntities($params);

        return empty($pavs['total']) ? null : $pavs['collection'];
    }

    protected function setAttributesFieldsForProduct(Entity $product, array $attributeFields, EntityCollection $pavs): void
    {
        foreach ($attributeFields as $attributeField) {
            $fieldName = $attributeField['fieldName'];
            $language = empty($attributeField['multilangLocale']) ? 'main' : $attributeField['multilangLocale'];
            foreach ($pavs as $pav) {
                if ($attributeField['attributeId'] === $pav->get('attributeId') && $pav->get('productId') === $product->get('id') && $pav->get('language') === $language) {
                    $product->set($fieldName, $pav->get('value'));
                    $product->set($fieldName . 'UnitId', $pav->get('valueUnitId'));
                    switch ($pav->get('attributeType')) {
                        case 'rangeInt':
                        case 'rangeFloat':
                            $product->set($fieldName . 'From', $pav->get('From'));
                            $product->set($fieldName . 'To', $pav->get('To'));
                            break;
                        case 'asset':
                            if (!empty($attributeField['assetFieldName'])) {
                                $product->set($attributeField['assetFieldName'] . 'Id', $pav->get('valueId'));
                                $product->set($attributeField['assetFieldName'] . 'Name', $pav->get('valueName'));
                                $product->set($attributeField['assetFieldName'] . 'PathsData', $pav->get('valuePathsData'));
                            }
                            break;
                    }
                }
            }
        }
        $product->attributesFieldsIsSet = true;
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
                    $parentValue = $parentPav->get('value');
                    if ($childPav->get('attributeType') === 'asset') {
                        $value = $childPav->get('valueId');
                        $parentValue = $parentPav->get('valueId');
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
}
