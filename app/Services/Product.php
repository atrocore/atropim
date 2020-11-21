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

use Dam\Entities\AssetRelation;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;
use Espo\ORM\EntityCollection;
use Treo\Core\EventManager\Event;
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
     * @param AssetRelation $assetRelation
     *
     * @return bool
     */
    public static function isMainRole(AssetRelation $assetRelation): bool
    {
        return in_array('Main', (array)$assetRelation->get('role'));
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
                    if (count($item->get('channels')) > 0) {
                        foreach ($item->get('channels') as $channel) {
                            $this
                                ->getEntityManager()
                                ->getRepository('ProductAttributeValue')
                                ->relate($entity, 'channels', $channel);
                        }
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

    /**
     * Find linked AssociationMainProduct
     *
     * @param string $id
     * @param array  $params
     *
     * @return array
     * @throws Forbidden
     */
    protected function findLinkedEntitiesAssociatedMainProducts(string $id, array $params): array
    {
        // check acl
        if (!$this->getAcl()->check('Association', 'read')) {
            throw new Forbidden();
        }

        return [
            'list'  => $this->getDBAssociationMainProducts($id, '', $params),
            'total' => $this->getDBTotalAssociationMainProducts($id, '')
        ];
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

        $link = 'productAttributeValues';

        if (!empty($params['maxSize'])) {
            $params['maxSize'] = $params['maxSize'] + 1;
        }

        // get select params
        $selectParams = $this->getSelectManager($foreignEntityName)->getSelectParams($params, true);

        // get record service
        $recordService = $this->getRecordService($foreignEntityName);

        /**
         * Prepare select list
         */
        $selectAttributeList = $recordService->getSelectAttributeList($params);
        if ($selectAttributeList) {
            $selectAttributeList[] = 'ownerUserId';
            $selectAttributeList[] = 'assignedUserId';
            if ($this->getConfig()->get('isMultilangActive')) {
                foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                    $selectAttributeList[] = Util::toCamelCase('owner_user_' . strtolower($locale) . '_id');
                    $selectAttributeList[] = Util::toCamelCase('assigned_user_' . strtolower($locale) . '_id');
                }
            }
            $selectParams['select'] = array_unique($selectAttributeList);
        }

        $collection = $this->getRepository()->findRelated($entity, $link, $selectParams);

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

        /**
         * For attribute locales
         */
        if (!empty($result['total']) && $this->getConfig()->get('isMultilangActive')) {
            $newCollection = new EntityCollection();
            foreach ($result['collection'] as $pav) {
                $pav->set('isLocale', false);
                $newCollection->append($pav);
                if (!empty($pav->get('attributeIsMultilang'))) {
                    foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                        $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($locale)));

                        $localePav = clone $pav;
                        $localePav->id = $localePav->id . ProductAttributeValue::LOCALE_IN_ID_SEPARATOR . $locale;
                        $localePav->set('isLocale', true);
                        $localePav->set('attributeName', $localePav->get('attributeName') . ' › ' . $locale);
                        $localePav->set('typeValue', $localePav->get("typeValue{$camelCaseLocale}"));
                        $localePav->set('value', $localePav->get("value{$camelCaseLocale}"));
                        $localePav->set('ownerUserId', $localePav->get("ownerUser{$camelCaseLocale}Id"));
                        $localePav->set('assignedUserId', $localePav->get("assignedUser{$camelCaseLocale}Id"));

                        $newCollection->append($localePav);
                        $result['total']++;
                    }
                }
            }

            $result['collection'] = $newCollection;
        }

        /**
         * Check every pav by ACL
         */
        if (!empty($result['total'])) {
            $newCollection = new EntityCollection();
            foreach ($result['collection'] as $pav) {
                $result['total']--;
                if ($this->getAcl()->check($pav, 'read')) {
                    $newCollection->append($pav);
                    $result['total']++;
                }
            }
            $result['collection'] = $newCollection;
        }

        return $this
            ->dispatchEvent('afterFindLinkedEntities', new Event(['id' => $id, 'link' => $link, 'params' => $params, 'result' => $result]))
            ->getArgument('result');
    }

    /**
     * Get AssociationMainProducts from DB
     *
     * @param string $productId
     * @param string $wherePart
     * @param array  $params
     *
     * @return array
     */
    protected function getDBAssociationMainProducts(string $productId, string $wherePart, array $params): array
    {
        // prepare limit
        $limit = '';
        if (!empty($params['maxSize'])) {
            $limit = ' LIMIT ' . (int)$params['maxSize'];
            $limit .= ' OFFSET ' . (empty($params['offset']) ? 0 : (int)$params['offset']);
        }

        //prepare sort
        $sortOrder = ($params['asc'] === true) ? 'ASC' : 'DESC';
        $orderColumn = ['relatedProduct', 'association'];
        $sortColumn = in_array($params['sortBy'], $orderColumn) ? $params['sortBy'] . '.name' : 'relatedProduct.name';

        $stringTypes = $this->getStringProductTypes();

        $selectFields = '
                  ap.id,
                  ap.association_id         AS associationId,
                  association.name          AS associationName,
                  p_main.id                 AS mainProductId,
                  p_main.name               AS mainProductName,
                  relatedProduct.id         AS relatedProductId,
                  relatedProduct.name       AS relatedProductName';

        if (!empty($this->getMetadata()->get('entityDefs.Product.fields.image'))) {
            $selectFields .= '
                ,
                p_main.image_id           AS mainProductImageId,
                (SELECT name FROM attachment WHERE id = p_main.image_id) AS mainProductImageName,
                relatedProduct.image_id   AS relatedProductImageId,
                (SELECT name FROM attachment WHERE id = relatedProduct.image_id) AS relatedProductImageName';
        }
        // prepare query
        $sql
            = "SELECT {$selectFields}
                FROM associated_product AS ap
                  JOIN product AS relatedProduct 
                    ON relatedProduct.id = ap.related_product_id AND relatedProduct.deleted = 0
                  JOIN product AS p_main 
                    ON p_main.id = ap.main_product_id AND p_main.deleted = 0
                  JOIN association 
                    ON association.id = ap.association_id AND association.deleted = 0
                WHERE ap.deleted = 0 
                  AND ap.main_product_id = :id AND relatedProduct.type IN ('{$stringTypes}') "
            . $wherePart
            . "ORDER BY " . $sortColumn . " " . $sortOrder
            . $limit;

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute([':id' => $productId]);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get total AssociationMainProducts
     *
     * @param string $productId
     * @param string $wherePart
     *
     * @return int
     */
    protected function getDBTotalAssociationMainProducts(string $productId, string $wherePart): int
    {
        $stringTypes = $this->getStringProductTypes();

        // prepare query
        $sql
            = "SELECT
                  COUNT(ap.id)                  
                FROM associated_product AS ap
                  JOIN product AS p_rel 
                    ON p_rel.id = ap.related_product_id AND p_rel.deleted = 0
                  JOIN product AS p_main 
                    ON p_main.id = ap.related_product_id AND p_main.deleted = 0
                  JOIN association 
                    ON association.id = ap.association_id AND association.deleted = 0
                WHERE ap.deleted = 0 AND ap.main_product_id = :id  AND p_rel.type IN ('{$stringTypes}') " . $wherePart;

        $sth = $this->getEntityManager()->getPDO()->prepare($sql);
        $sth->execute([':id' => $productId]);

        return (int)$sth->fetchColumn();
    }

    /**
     * Before create entity method
     *
     * @param Entity $entity
     * @param        $data
     */
    protected function beforeCreateEntity(Entity $entity, $data)
    {
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
}
