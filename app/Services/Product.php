<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Services\Hierarchy;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\Services\MassActions;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Treo\Core\Exceptions\NotModified;

class Product extends Hierarchy
{
    protected $mandatorySelectAttributeList = ['data'];

    public function loadPreviewForCollection(EntityCollection $collection): void
    {
        // set global main images
        if (count($collection) > 0 && !empty($records = $this->getRepository()->getProductsAssets(array_column($collection->toArray(), 'id')))) {
            foreach ($collection as $entity) {
                if (!isset($records[$entity->get('id')])) {
                    continue 1;
                }

                $assetsCollection = $records[$entity->get('id')];

                if (count($assetsCollection) === 0) {
                    continue 1;
                }

                $entity->set('mainImageId', null);
                $entity->set('mainImageName', null);
                foreach ($this->getPreparedProductAssets($entity->get('id'), $assetsCollection, $this->getPrismChannelId()) as $asset) {
                    if (!empty($asset->get('isGlobalMainImage'))) {
                        $entity->set('mainImageId', $asset->get('fileId'));
                        $entity->set('mainImageName', $asset->get('fileName'));
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
        if (!$entity->has('mainImageId')) {
            $entity->set('mainImageId', null);
            $entity->set('mainImageName', null);
            $entity->set('mainImagePathsData', null);

            if (!empty($records = $this->getEntityManager()->getRepository('Product')->getProductsAssets([$entity->get('id')])) && isset($records[$entity->get('id')])) {
                $assetsCollection = $records[$entity->get('id')];
                if (count($assetsCollection) > 0) {
                    foreach ($this->getPreparedProductAssets($entity->get('id'), $assetsCollection, $this->getPrismChannelId()) as $asset) {
                        if (!empty($asset->get('isGlobalMainImage'))) {
                            $entity->set('mainImageId', $asset->get('fileId'));
                            $entity->set('mainImageName', $asset->get('fileName'));
                            $entity->set('mainImagePathsData', $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($asset->get('fileId')));
                        }
                    }
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

        if ($this->isProductAttributeUpdating($data)) {
            if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->beginTransaction();
                $inTransaction = true;
            }
            $service = $this->getInjection('serviceFactory')->create('ProductAttributeValue');

            $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['productId' => $id])->find();

            foreach ($data->panelsData->productAttributeValues as $pavId => $pavData) {
                $existPavId = $this->getProductAttributeIdForUpdating($pavs, $pavData, (string)$pavId);

                try {
                    $copy = clone $pavData;

                    if (!is_null($existPavId)) {
                        if (!empty($data->_ignoreConflict)) {
                            $copy->_prev = null;
                        }

                        $copy->isProductUpdate = true;

                        unset($copy->attributeId);
                        unset($copy->channelId);
                        unset($copy->channelName);
                        unset($copy->language);

                        $service->updateEntity($existPavId, $copy);
                    } else {
                        $isValidChannel = true;

                        if (property_exists($copy, 'channelId') && !empty($copy->channelId)) {
                            $channelIds = $this->getEntityManager()->getRepository('ProductChannel')->select(['channelId'])->where(['productId' => $id])->find()->toArray();
                            $channelIds = array_column($channelIds, 'channelId');

                            if (!in_array($copy->channelId, $channelIds)) {
                                $isValidChannel = false;
                            }
                        }

                        if ($isValidChannel) {
                            $copy->productId = $id;

                            $result = $service->createEntity($copy);

                            if (!$result->get('attributeIsMultilang')) {
                                $pavs->append($result);
                            } else {
                                $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')->where(['productId' => $id])->find();
                            }
                        }
                    }
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
            || !property_exists($data, 'associationId') || empty($data->associationId)) {
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
        if (!property_exists($data, 'foreignWhere') || !is_array($data->foreignWhere)
            || !property_exists($data, 'associationId') || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        $selectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->where), true)], true);

        $products = $this->getRepository()->select(['id'])->find($selectParams);
        $ids = array_column($products->toArray(), 'id');

        $foreignSelectParams = $this->getSelectManager()->getSelectParams(['where' => Json::decode(Json::encode($data->foreignWhere), true)], true);

        $foreignProducts = $this->getRepository()->select(['id'])->find($foreignSelectParams);
        $foreignIds = array_column($foreignProducts->toArray(), 'id');

        $associatedProducts = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->where(
                [
                    'associationId'    => $data->associationId,
                    'mainProductId'    => $ids,
                    'relatedProductId' => $foreignIds
                ]
            )
            ->find();

        $exists = [];
        if ($associatedProducts->count() > 0) {
            foreach ($associatedProducts as $item) {
                $exists[$item->get('mainProductId') . '_' . $item->get('relatedProductId')] = $item;
            }
        }

        $success = 0;
        $error = [];
        foreach ($ids as $id) {
            foreach ($foreignIds as $foreignId) {
                $success++;
                if (isset($exists["{$id}_{$foreignId}"])) {
                    $associatedProduct = $exists["{$id}_{$foreignId}"];
                    try {
                        $this->getEntityManager()->removeEntity($associatedProduct);
                    } catch (BadRequest $e) {
                        $success--;
                        $error[] = [
                            'id'          => $associatedProduct->get('mainProductId'),
                            'name'        => $associatedProduct->get('mainProduct')->get('name'),
                            'foreignId'   => $associatedProduct->get('relatedProductId'),
                            'foreignName' => $associatedProduct->get('relatedProduct')->get('name'),
                            'message'     => utf8_encode($e->getMessage())
                        ];
                    }
                }
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
                if (!empty($duplicate = $repository->findCopy($entity))) {
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

    public function findLinkedEntities($id, $link, $params)
    {
        $result = parent::findLinkedEntities($id, $link, $params);

        /**
         * Set global main image
         */
        if ($link === 'assets' && $result['total'] > 0 && isset($result['collection'])) {
            $channelId = isset($params['exportByChannelId']) ? $params['exportByChannelId'] : $this->getPrismChannelId();
            $result['collection'] = $this->getPreparedProductAssets($id, $result['collection'], $channelId);
            $result['total'] = $result['collection']->count();

            return $result;
        }

        /**
         * Mark channels as inherited from categories
         */
        if ($link === 'productChannels' && $result['total'] > 0) {
            $channelsIds = $this->getEntityManager()->getRepository('Product')->getCategoriesChannelsIds($id);
            if (!empty($channelsIds)) {
                foreach ($result['collection'] as $record) {
                    $record->set('isInherited', in_array($record->get('channelId'), $channelsIds));
                }
            }

            return $result;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function linkEntity($id, $link, $foreignId)
    {
        $result = parent::linkEntity($id, $link, $foreignId);

        if (!empty($result) && $link === 'channels') {
            $product = $this->getEntity($id);

            if ($product) {
                $pfas = $this
                    ->getEntityManager()
                    ->getRepository('ProductFamilyAttribute')
                    ->where([
                        'productFamilyId' => $product->get('productFamilyId'),
                        'scope'           => 'Channel',
                        'channelId'       => $foreignId
                    ])
                    ->find();

                if (count($pfas) > 0) {
                    /** @var ProductAttributeValue $service */
                    $service = $this->getInjection('serviceFactory')->create('ProductAttributeValue');

                    foreach ($pfas as $pfa) {
                        $data = new \stdClass();
                        $data->attributeId = $pfa->get('attributeId');
                        $data->productId = $id;
                        $data->scope = $pfa->get('scope');
                        $data->channelId = $pfa->get('channelId');
                        $data->channelName = $pfa->get('channelName');

                        try {
                            $service->createEntity($data);
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }
        }

        return $result;
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

        if (!$this->getAcl()->check($foreignEntityName, 'read')) {
            throw new Forbidden();
        }

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
            $selectAttributeList[] = 'ownerUserId';
            $selectAttributeList[] = 'assignedUserId';
            $selectParams['select'] = array_unique($selectAttributeList);
        }

        $collection = $this->getEntityManager()->getRepository('Product')->findRelated($entity, $link, $selectParams);
        $recordService->prepareCollectionForOutput($collection);
        foreach ($collection as $e) {
            $recordService->loadAdditionalFieldsForList($e);
            if (!empty($params['loadAdditionalFields'])) {
                $recordService->loadAdditionalFields($e);
            }
            if (!empty($selectAttributeList)) {
                $this->loadLinkMultipleFieldsForList($e, $selectAttributeList);
            }
            $recordService->prepareEntityForOutput($e);
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
        if (!empty($collection[0]) && empty($collection[0]->has('scope'))) {
            $pavsData = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id', 'scope', 'channelId', 'channelName'])
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

        // filtering pavs by scope and channel languages
        foreach ($collection as $pav) {
            if (!isset($scopeData[$pav->get('id')])) {
                continue 1;
            }

            if ($scopeData[$pav->get('id')]->get('scope') === 'Global') {
                $records[$pav->get('id')] = $pav;
            } elseif ($scopeData[$pav->get('id')]->get('scope') === 'Channel' && !empty($scopeData[$pav->get('id')]->get('channelId'))) {
                if (empty($pav->get('attributeIsMultilang'))) {
                    $records[$pav->get('id')] = $pav;
                } else {
                    if (in_array($pav->get('language'), $pav->getChannelLanguages())) {
                        $records[$pav->get('id')] = $pav;
                    }
                }
            }
        }

        // clear hided records
        foreach ($collection as $pav) {
            if (!isset($records[$pav->get('id')])) {
                $this->getEntityManager()->getRepository('ProductAttributeValue')->clearRecord($pav->get('id'));
            }
        }

        $headerLanguage = self::getHeader('language');

        // filtering via header language
        if (!empty($headerLanguage)) {
            foreach ($records as $id => $pav) {
                if ($pav['language'] !== $headerLanguage) {
                    unset($records[$id]);
                }
            }
        }

        foreach ($records as $pav) {
            if ($pav->get('scope') === 'Global') {
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
                if ($scopeData[$pav->get('id')]->get('scope') === 'Global' && !in_array($pav->get('attributeId'), $channelSpecificAttributeIds)) {
                    $newCollection->append($pav);
                }
            }

            $collection = $newCollection;
        }

        return $collection;
    }

    /**
     * Before create entity method
     *
     * @param Entity $entity
     * @param        $data
     */
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

        $asset = $this->getEntityManager()->getRepository('Asset')->where(['fileId' => $data->mainImageId])->findOne();
        if (empty($asset)) {
            return;
        }

        $this->linkEntity($entity->get('id'), 'assets', $asset->get('id'));
        $this->getRepository()->updateRelationData('productAsset', ['isMainImage' => true], 'productId', $entity->get('id'), 'assetId', $asset->get('id'));
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
        $select = ['id', 'attributeId', 'attributeName', 'isRequired', 'scope', 'channelId', 'channelName', 'data', 'value'];
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
        return !empty($data->panelsData->productAttributeValues);
    }

    protected function getProductAttributeIdForUpdating(EntityCollection $pavs, \stdClass $data, string $id): ?string
    {
        if (!property_exists($data, 'attributeId') || !property_exists($data, 'scope') || !property_exists($data, 'language')) {
            $pavsIds = array_column($pavs->toArray(), 'id');

            if (in_array($id, $pavsIds)) {
                return $id;
            }
        } else {
            foreach ($pavs as $pav) {
                if ($pav->get('attributeId') == $data->attributeId && $pav->get('scope') == $data->scope && $pav->get('language') == $data->language) {
                    if ($data->scope == 'Global' || ($data->scope === 'Channel' && property_exists($data, 'channelId') && $pav->get('channelId') == $data->channelId)) {
                        return $pav->id;
                    }
                }
            }
        }

        return null;
    }


    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        $post = clone $data;

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

    protected function getPreparedProductAssets(string $productId, EntityCollection $assets, ?string $channelId): EntityCollection
    {
        if (empty($channelId)) {
            foreach ($assets as $asset) {
                $asset->set('isGlobalMainImage', !empty($asset->get('isMainImage')) && empty($asset->get('channel')) && empty($asset->get('mainImageForChannel')));
            }
            return $assets;
        }

        $newCollection = new EntityCollection();

        $hasMainImage = false;
        foreach ($assets as $asset) {
            $asset->set('isGlobalMainImage', false);
            if (!$asset->has('channel')) {
                $assetData = $this->getEntityManager()->getRepository('Product')->getAssetData($productId, $asset->get('id'));
                $asset->set('channel', $assetData['channel']);
                $mainImageForChannel = @json_decode((string)$assetData['main_image_for_channel'], true);
                $asset->set('mainImageForChannel', empty($mainImageForChannel) ? [] : $mainImageForChannel);
            }

            if (!empty($asset->get('channel')) && $asset->get('channel') !== $channelId) {
                continue 1;
            }

            if (!empty($asset->get('isMainImage'))) {
                if (in_array($channelId, $asset->get('mainImageForChannel')) || $asset->get('channel') === $channelId) {
                    $asset->set('isGlobalMainImage', true);
                    $hasMainImage = true;
                }
            }

            $newCollection->append($asset);
        }

        if (!$hasMainImage) {
            foreach ($assets as $asset) {
                $asset->set('isGlobalMainImage', !empty($asset->get('isMainImage')) && empty($asset->get('channel')) && empty($asset->get('mainImageForChannel')));
            }
        }

        return $newCollection;
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
                    'attribute' => 'scope',
                    'value'     => 'Global'
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
                    switch ($pav->get('attributeType')) {
                        case 'unit':
                            $product->set($fieldName . 'Unit', $pav->get('valueUnit'));
                            break;
                        case 'currency':
                            $product->set($fieldName . 'Currency', $pav->get('valueCurrency'));
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
}
