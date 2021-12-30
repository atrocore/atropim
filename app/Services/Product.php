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
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Treo\Core\EventManager\Event;
use Treo\Core\Exceptions\NotModified;
use Treo\Services\MassActions;

/**
 * Service of Product
 */
class Product extends AbstractService
{
    /**
     * @var string
     */
    protected $linkWhereNeedToUpdateChannel = 'productAttributeValues';

    /**
     * @inheritDoc
     */
    public function unlinkEntity($id, $link, $foreignId)
    {
        if ($link == 'assets') {
            return $this->unlinkAssets($id, $foreignId);
        }

        return parent::unlinkEntity($id, $link, $foreignId);
    }

    public function unlinkAssets(string $id, string $foreignId): bool
    {
        $link = 'assets';

        $parts = explode('_', $foreignId);
        $foreignId = array_shift($parts);
        $channel = implode('_', $parts);

        $event = $this->dispatchEvent('beforeUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignId' => $foreignId]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $foreignId = $event->getArgument('foreignId');

        if (empty($id) || empty($link) || empty($foreignId)) {
            throw new BadRequest;
        }

        if (in_array($link, $this->readOnlyLinkList)) {
            throw new Forbidden();
        }

        $entity = $this->getRepository()->get($id);
        if (!$entity) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($entity, 'edit')) {
            throw new Forbidden();
        }

        $foreignEntityType = $entity->getRelationParam($link, 'entity');
        if (!$foreignEntityType) {
            throw new Error("Entity '{$this->entityType}' has not relation '{$link}'.");
        }

        $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $foreignId);
        if (!$foreignEntity) {
            throw new NotFound();
        }

        $accessActionRequired = 'edit';
        if (in_array($link, $this->noEditAccessRequiredLinkList)) {
            $accessActionRequired = 'read';
        }
        if (!$this->getAcl()->check($foreignEntity, $accessActionRequired)) {
            throw new Forbidden();
        }

        $sql = "DELETE FROM product_asset WHERE asset_id='$foreignId' AND product_id='$id'";

        if (empty($channel)) {
            $sql .= " AND (channel IS NULL OR channel='')";
        } else {
            $sql .= " AND channel='$channel'";
        }

        $this->getEntityManager()->nativeQuery($sql);

        return $this
            ->dispatchEvent('afterUnlinkEntity', new Event(['id' => $id, 'link' => $link, 'foreignEntity' => $foreignEntity, 'result' => true]))
            ->getArgument('result');
    }

    public function updateActiveForChannel(string $channelId, string $productId, bool $isActive): bool
    {
        if (empty($channel = $this->getEntityManager()->getEntity('Channel', $channelId)) || !$this->getAcl()->check($channel, 'edit')) {
            return false;
        }

        if (empty($product = $this->getEntityManager()->getEntity('Product', $productId)) || !$this->getAcl()->check($product, 'edit')) {
            return false;
        }

        $this->getRepository()->updateChannelRelationData($productId, $channelId, $isActive);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function updateEntity($id, $data)
    {
        $conflicts = [];
        if ($this->isProductAttributeUpdating($data)) {
            if (!$this->getEntityManager()->getPDO()->inTransaction()) {
                $this->getEntityManager()->getPDO()->beginTransaction();
                $inTransaction = true;
            }
            $service = $this->getInjection('serviceFactory')->create('ProductAttributeValue');
            foreach ($data->panelsData->productAttributeValues as $pavId => $pavData) {
                if (!empty($data->_ignoreConflict)) {
                    $pavData->_prev = null;
                }
                $pavData->isProductUpdate = true;
                try {
                    $service->updateEntity($pavId, $pavData);
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
        if (empty($data->ids) || empty($data->foreignIds) || empty($data->associationId) || !is_array($data->ids) || !is_array($data->foreignIds) || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        /** @var Entity $association */
        $association = $this->getEntityManager()->getEntity("Association", $data->associationId);
        if (empty($association)) {
            throw new BadRequest($this->exception('noSuchAssociation'));
        }

        /**
         * Collect entities for saving
         */
        $toSave = [];
        foreach ($data->ids as $mainProductId) {
            foreach ($data->foreignIds as $relatedProductId) {
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set("associationId", $data->associationId);
                $entity->set("mainProductId", $mainProductId);
                $entity->set("relatedProductId", $relatedProductId);

                if (!empty($backwardAssociationId = $association->get('backwardAssociationId'))) {
                    $entity->set('backwardAssociationId', $backwardAssociationId);
                    $entity->set("bothDirections", true);

                    $backwardEntity = $this->getEntityManager()->getEntity('AssociatedProduct');
                    $backwardEntity->set("associationId", $backwardAssociationId);
                    $backwardEntity->set("mainProductId", $entity->get('relatedProductId'));
                    $backwardEntity->set("relatedProductId", $entity->get('mainProductId'));
                    $backwardEntity->set("bothDirections", true);
                    $backwardEntity->set("backwardAssociationId", $entity->get('associationId'));

                    $toSave[] = $backwardEntity;
                }

                $toSave[] = $entity;
            }
        }

        $error = [];
        foreach ($toSave as $entity) {
            try {
                $this->getEntityManager()->saveEntity($entity);
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
        if (empty($data->ids) || empty($data->foreignIds) || empty($data->associationId) || !is_array($data->ids) || !is_array($data->foreignIds) || empty($data->associationId)) {
            throw new BadRequest($this->exception('wrongInputData'));
        }

        $associatedProducts = $this
            ->getEntityManager()
            ->getRepository('AssociatedProduct')
            ->where(
                [
                    'associationId'    => $data->associationId,
                    'mainProductId'    => $data->ids,
                    'relatedProductId' => $data->foreignIds
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
        foreach ($data->ids as $id) {
            foreach ($data->foreignIds as $foreignId) {
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

    /**
     * @inheritDoc
     */
    public function setAsMainImage(string $assetId, string $entityId, ?string $scope): array
    {
        $parts = explode('_', $assetId);
        $assetId = array_shift($parts);

        if (empty($asset = $this->getEntityManager()->getEntity('Asset', $assetId)) || empty($attachment = $asset->get('file'))) {
            throw new NotFound();
        }

        /** @var \Pim\Entities\Product $entity */
        $entity = $this->getRepository()->get($entityId);
        if (empty($entity)) {
            throw new NotFound();
        }

        if (empty($scope)) {
            throw new BadRequest();
        }

        if (!is_array($data = $entity->getDataField('mainImages'))) {
            $data = [];
        }

        if ($scope == 'Global') {
            $entity->set('imageId', $asset->get('fileId'));
            if (isset($data[$asset->id])) {
                unset($data[$asset->id]);

                $entity->setDataField('mainImages', $data);
            }

            $this->getEntityManager()->saveEntity($entity);

            return [
                'imageId'        => $asset->get('fileId'),
                'imageName'      => $asset->get('name'),
                'imagePathsData' => $this->getEntityManager()->getRepository('Attachment')->getAttachmentPathsData($attachment)
            ];
        } elseif ($scope == 'Channel') {
            $channels = [array_shift($parts)];

            $data = $this->getPreparedProductAssetData($data, $channels);
            $data[$asset->id] = $channels;

            $entity->setDataField('mainImages', $data);
            $this->getEntityManager()->saveEntity($entity);
        }

        return [];
    }

    /**
     * @param array $data
     * @param array $channelsIds
     *
     * @return array
     */
    public function getPreparedProductAssetData(array $data, array $channelsIds): array
    {
        foreach ($data as $assetId => $assetData) {
            $diff = array_values(array_diff($assetData, $channelsIds));

            if (empty($diff)) {
                unset($data[$assetId]);
            } else {
                $data[$assetId] = $diff;
            }
        }

        return $data;
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

    /**
     * @param Entity $product
     * @param Entity $duplicatingProduct
     */
    protected function duplicateProductAttributeValues(Entity $product, Entity $duplicatingProduct)
    {
        if ($duplicatingProduct->get('productFamilyId') == $product->get('productFamilyId')) {
            // get data for duplicating
            $rows = $duplicatingProduct->get('productAttributeValues');

            if (count($rows) > 0) {
                foreach ($rows as $item) {
                    $entity = $this->getEntityManager()->getEntity('ProductAttributeValue');
                    $entity->set($item->toArray());
                    $entity->id = Util::generateId();
                    $entity->set('productId', $product->get('id'));

                    $this->getEntityManager()->saveEntity($entity, ['skipProductAttributeValueHook' => true]);

                    // relate channels
                    if (!empty($channel = $item->get('channel'))) {
                        $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->relate($entity, 'channel', $channel);
                    }
                }
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

                // prepare entity
                $entity = $this->getEntityManager()->getEntity('AssociatedProduct');
                $entity->set($item);

                // save
                $this->getEntityManager()->saveEntity($entity);
            }
        }
    }

    protected function findLinkedEntitiesAssets(string $id, array $params): array
    {
        $event = $this->dispatchEvent('beforeFindLinkedEntities', new Event(['id' => $id, 'link' => 'assets', 'params' => $params]));

        $id = $event->getArgument('id');
        $link = $event->getArgument('link');
        $params = $event->getArgument('params');

        $result = ['list' => []];

        $productAssets = $this->getInjection('serviceFactory')->create('Asset')->getEntityAssets('Product', $id);
        if (!empty($productAssets['count'])) {
            $channelId = isset($params['exportByChannelId']) ? $params['exportByChannelId'] : $this->getPrismChannelId();
            foreach ($productAssets['list'] as $assetType) {
                if (!empty($assetType['assets'])) {
                    foreach ($assetType['assets'] as $asset) {
                        if (!empty($channelId) && $asset['scope'] === 'Channel' && $asset['channelId'] !== $channelId) {
                            continue 1;
                        }
                        $result['list'][] = $asset;
                    }
                }
            }
        }
        $result['total'] = count($result['list']);

        return $this->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))->getArgument('result');
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
        $entity = $this->getRepository()->get($id);
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

        $this->getRepository()->updateInconsistentAttributes($entity);

        $link = 'productAttributeValues';

        if (!empty($params['maxSize'])) {
            $params['maxSize'] = $params['maxSize'] + 1;
        }

        // get select params
        $selectParams = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);
        $selectParams['orderBy'] = 'id';

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

        $collection = $this->filterPavsViaChannel($this->getRepository()->findRelated($entity, $link, $selectParams));

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

        $result = [
            'total'      => $this->getRepository()->countRelated($entity, $link, $selectParams),
            'collection' => $collection
        ];

        // prepare result
        if (!empty($result['total'])) {
            $records = [];
            foreach ($result['collection'] as $pav) {
                if ($pav->get('scope') === 'Global') {
                    $records[$pav->get('id')] = $pav;
                } elseif ($pav->get('scope') === 'Channel' && !empty($channel = $pav->get('channel')) && in_array($pav->get('language'), $channel->get('locales'))) {
                    $records[$pav->get('id')] = $pav;
                }
            }

            $headerLanguage = self::getHeader('language');

            // filtering via header language
            if (!empty($headerLanguage)) {
                foreach ($records as $pav) {
                    if (!empty($pav->get('mainLanguageId')) && isset($records[$pav->get('mainLanguageId')])) {
                        unset($records[$pav->get('mainLanguageId')]);
                    }
                }
            }

            // sorting via languages
            if (empty($headerLanguage)) {
                $newRecords = [];
                foreach ($records as $pav) {
                    if (empty($pav->get('mainLanguageId'))) {
                        $newRecords[] = $pav;
                        foreach ($this->getConfig()->get('inputLanguageList', []) as $language) {
                            foreach ($records as $pav1) {
                                if ($pav1->get('mainLanguageId') === $pav->get('id') && $language === $pav1->get('language')) {
                                    $newRecords[] = $pav1;
                                }
                            }
                        }
                    }
                }
                $records = $newRecords;
            }

            $result['total'] = count($records);
            $result['collection'] = new EntityCollection(array_values($records));
        }

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))
            ->getArgument('result');
    }

    protected function filterPavsViaChannel(EntityCollection $collection): EntityCollection
    {
        if (count($collection) > 0 && !empty($channelId = $this->getPrismChannelId())) {
            $newCollection = new EntityCollection();

            $channelSpecificAttributeIds = [];
            foreach ($collection as $pav) {
                if ($pav->get('channelId') === $channelId) {
                    $channelSpecificAttributeIds[] = $pav->get('attributeId');
                    $newCollection->append($pav);
                }
            }

            foreach ($collection as $pav) {
                if ($pav->get('scope') === 'Global' && !in_array($pav->get('attributeId'), $channelSpecificAttributeIds)) {
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
     * @param Entity $entity
     *
     * @return string|null
     */
    protected function getAttributeChannelsFromEntityForExport(Entity $entity): ?string
    {
        if (empty($entity->get('channelsIds'))) {
            return null;
        }

        $channelRelationData = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->getChannelRelationData($entity->get('id'));

        $result = [];
        foreach ($entity->get('channelsNames') as $id => $name) {
            $result[] = [
                'id'       => $id,
                'name'     => $name,
                'isActive' => $channelRelationData[$id]['isActive']
            ];
        }

        return Json::encode($result);
    }

    /**
     * @return string
     */
    protected function getStringProductTypes(): string
    {
        return join("','", array_keys($this->getMetadata()->get('pim.productType')));
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
        return $this->getTranslate($key, 'exceptions', 'Product');
    }

    /**
     * @param \stdClass $data
     *
     * @return bool
     */
    protected function isProductAttributeUpdating(\stdClass $data): bool
    {
        return !empty($data->panelsData->productAttributeValues);
    }

    /**
     * @inheritDoc
     */
    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        if ($this->isProductAttributeUpdating($data)) {
            return true;
        }

        return parent::isEntityUpdated($entity, $data);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}
