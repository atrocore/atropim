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

namespace Pim\Repositories;

use Atro\Core\PseudoTransactionManager;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Atro\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\Repositories\RDB;
use Pim\Core\Exceptions\ProductAttributeAlreadyExists;
use Pim\Core\ValueConverter;

class ProductAttributeValue extends AbstractAttributeValue
{
    protected static $beforeSaveData = [];

    protected array $channelLanguages = [];
    protected array $productCa = [];

    public function isInherited(Entity $entity): ?bool
    {
        return null;
    }

    public function getPavsWithAttributeGroupsData(string $productId, string $tabId, string $language, array $languageFilter, array $scopeFilter): array
    {
        // prepare tabId
        if ($tabId === 'null') {
            $tabId = null;
        }

        $qb = $this->getConnection()->createQueryBuilder();
        $qb->select('pav.id, pav.attribute_id, pav.channel_id, c.name as channel_name, pav.language')
            ->from('product_attribute_value', 'pav')
            ->leftJoin('pav', 'channel', 'c', 'pav.channel_id=c.id AND c.deleted=:false')
            ->where('pav.deleted=:false')->setParameter('false', false, ParameterType::BOOLEAN)
            ->andWhere('pav.product_id=:productId')->setParameter('productId', $productId);
        if (empty($tabId)) {
            $qb->andWhere('pav.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id IS NULL AND deleted=:false)');
        } else {
            $qb->andWhere('pav.attribute_id IN (SELECT id FROM attribute WHERE attribute_tab_id=:tabId AND deleted=:false)')->setParameter('tabId', $tabId);
        }


        if (!empty($languageFilter) && !in_array('allLanguages', $languageFilter)) {
            $query = '(pav.language IN (:languagesFilter) AND pav.attribute_id IN (SELECT id FROM attribute WHERE is_multilang=:true)) ';

            if (in_array('unilingual', $languageFilter)) {
                $languageFilter = array_filter($languageFilter, function ($lang) {
                    return $lang !== 'unilingual';
                });
                $query .= ' OR pav.attribute_id IN (SELECT id FROM attribute WHERE is_multilang=:false)';
            }

            $qb->andWhere($query)
                ->setParameter('true', true, ParameterType::BOOLEAN)
                ->setParameter('languagesFilter', $languageFilter, Connection::PARAM_STR_ARRAY);
        }

        if (!empty($scopeFilter) && !in_array('allChannels', $scopeFilter)) {
            $sql = "";
            if (in_array('linkedChannels', $scopeFilter)) {
                $sql .= "pav.channel_id IN (SELECT channel_id FROM product_channel where product_id =:productId and deleted=:false) OR ";
            }
            $scopeFilter = array_map(function ($v) {
                if ($v === 'Global' || $v === "linkedChannels") {
                    return '';
                }
                return $v;
            }, $scopeFilter);

            $sql .= 'pav.channel_id IN (:channelsIds)';

            $qb->andWhere($sql)
                ->setParameter('channelsIds', $scopeFilter, Connection::PARAM_STR_ARRAY);

        }

        $pavs = $qb->fetchAllAssociative();

        if (empty($pavs)) {
            return [];
        }

        $attrsIds = array_values(array_unique(array_column($pavs, 'attribute_id')));

        // prepare suffix
        $languageSuffix = '';
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            if (in_array($language, $this->getConfig()->get('inputLanguageList', []))) {
                $languageSuffix = '_' . strtolower($language);
            }
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('a.*, ag.name' . $languageSuffix . ' as attribute_group_name, ag.sort_order as attribute_group_sort_order')
            ->from('attribute', 'a')
            ->where('a.deleted=:false')
            ->leftJoin('a', 'attribute_group', 'ag', 'a.attribute_group_id=ag.id AND ag.deleted=:false')
            ->andWhere('a.id IN (:attributesIds)')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('attributesIds', $attrsIds, Connection::PARAM_STR_ARRAY);

        try {
            $attrs = $qb->fetchAllAssociative();
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Getting attributes failed: ' . $e->getMessage());
            return [];
        }

        foreach ($pavs as $k => $pav) {
            foreach ($attrs as $attr) {
                if ($attr['id'] === $pav['attribute_id']) {
                    $pavs[$k]['attribute_data'] = $attr;
                    break 1;
                }
            }
        }

        return $pavs;
    }

    public function getPavAttribute(Entity $entity): \Pim\Entities\Attribute
    {
        $attribute = $this->getEntityManager()->getEntity('Attribute', $entity->get('attributeId'));

        if (empty($attribute)) {
            $this->getConnection()->createQueryBuilder()
                ->delete('classification_attribute')
                ->where('attribute_id = :attributeId')
                ->setParameter('attributeId', $entity->get('attributeId'))
                ->executeQuery();

            $this->getConnection()->createQueryBuilder()
                ->delete('product_attribute_value')
                ->where('attribute_id = :attributeId')
                ->setParameter('attributeId', $entity->get('attributeId'))
                ->executeQuery();

            throw new BadRequest("Attribute '{$entity->get('attributeId')}' does not exist.");
        }

        return $attribute;
    }

    public function getChildPavForProduct(Entity $parentPav, Entity $childProduct): ?Entity
    {
        $where = [
            'productId'   => $childProduct->get('id'),
            'attributeId' => $parentPav->get('attributeId'),
            'language'    => $parentPav->get('language'),
            'channelId'   => $parentPav->get('channelId'),
        ];

        return $this->where($where)->findOne();
    }

    public function findClassificationAttribute(Entity $pav): ?array
    {
        $productId = $pav->get('productId');
        if (empty($productId)) {
            return null;
        }

        foreach ($this->getProductClassificationAttributes($productId) as $item) {
            if ($item['attributeId'] !== $pav->get('attributeId')) {
                continue;
            }

            if ($item['language'] !== $pav->get('language')) {
                continue;
            }

            if ($item['channelId'] !== $pav->get('channelId')) {
                continue;
            }

            return $item;
        }

        return null;
    }

    public function getProductClassificationAttributes(string $productId): array
    {
        if (!isset($this->productCa[$productId])) {
            $res = $this->getConnection()->createQueryBuilder()
                ->select('ca.*')
                ->from('classification_attribute', 'ca')
                ->leftJoin('ca', 'product_classification', 'pc', 'pc.classification_id = ca.classification_id')
                ->where('pc.product_id = :productId')
                ->andWhere('pc.deleted = :false')
                ->andWhere('ca.deleted = :false')
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->setParameter('productId', $productId)
                ->fetchAllAssociative();

            $result = [];
            foreach (Util::arrayKeysToCamelCase($res) as $k => $v) {
                $row = $v;
                unset($row['data']);

                $data = @json_decode($v['data'], true);
                if (!empty($data['field'])) {
                    $row = array_merge($row, $data['field']);
                }

                $result[] = $row;
            }

            $this->productCa[$productId] = $result;
        }

        return $this->productCa[$productId];
    }

    public function getChannelLanguages(string $channelId): array
    {
        if (empty($channelId)) {
            return [];
        }

        if (!isset($this->channelLanguages[$channelId])) {
            $this->channelLanguages[$channelId] = [];
            if (!empty($channel = $this->getEntityManager()->getRepository('Channel')->get($channelId))) {
                $this->channelLanguages[$channelId] = $channel->get('locales');
            }
        }

        return $this->channelLanguages[$channelId];
    }

    public function clearRecord(string $id): void
    {
        $this->getConnection()->createQueryBuilder()
            ->update($this->getConnection()->quoteIdentifier('product_attribute_value'), 'pav')
            ->set('varchar_value', ':null')
            ->set('text_value', ':null')
            ->set('bool_value', ':false')
            ->set('float_value', ':null')
            ->set('int_value', ':null')
            ->set('date_value', ':null')
            ->set('datetime_value', ':null')
            ->where('pav.id = :id')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('id', $id, Mapper::getParameterType($id))
            ->setParameter('null', null)
            ->executeQuery();
    }

    public function save(Entity $entity, array $options = [])
    {
        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));

        if ($entity->get('attributeType') == 'wysiwyg' && ($entity->isNew() || $entity->isAttributeChanged('value'))) {
            if (!empty($attribute)) {
                $htmlSanitizerId = $attribute->get('htmlSanitizerId');

                if (!empty($htmlSanitizerId)) {
                    /** @var \Atro\Services\HtmlSanitizer $service */
                    $service = $this->getInjection('container')->get('serviceFactory')->create('HtmlSanitizer');

                    $safeHtml = $service->sanitize($htmlSanitizerId, $entity->get('textValue'));
                    $entity->set('textValue', $safeHtml);
                }
            }
        }
        
        if (!empty($entity->get('channelId'))) {
            $channel = $this->getEntityManager()->getRepository('Channel')->get($entity->get('channelId'));
        }

        try {
            $result = parent::save($entity, $options);
        } catch (UniqueConstraintViolationException $e) {
            $attributeName = !empty($attribute) ? $attribute->get('name') : $entity->get('attributeId');
            $channelName = 'Global';
            if (!empty($entity->get('channelId'))) {
                $channelName = !empty($channel) ? $channel->get('name') : $entity->get('channelId');
            }

            throw new ProductAttributeAlreadyExists(
                sprintf($this->getInjection('language')->translate('attributeRecordAlreadyExists', 'exceptions'), $attributeName, $channelName)
            );
        }

        return $result;
    }

    public function removeByProductId(string $productId): void
    {
        $this
            ->where(['productId' => $productId])
            ->removeCollection();
    }

    public function removeByAttributeId(string $attributeId): void
    {
        $this
            ->where(['attributeId' => $attributeId])
            ->removeCollection();
    }

    public function getDuplicateEntity(Entity $entity, bool $deleted = false): ?Entity
    {
        $where = [
            'id!='        => $entity->get('id'),
            'language'    => $entity->get('language'),
            'productId'   => $entity->get('productId'),
            'attributeId' => $entity->get('attributeId'),
            'channelId'   => $entity->get('channelId'),
            'deleted'     => $deleted,
        ];

        return $this->where($where)->findOne(['withDeleted' => $deleted]);
    }

    protected function populateDefault(Entity $entity, Entity $attribute): void
    {
        $entity->set('attributeType', $attribute->get('type'));

        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        if (empty($entity->get('language'))) {
            $entity->set('language', 'main');
        }

        if ($entity->isNew()) {
            if (!empty($attribute->get('measureId')) && empty($entity->get('referenceValue')) && !empty($attribute->get('defaultUnit'))) {
                $entity->set('referenceValue', $attribute->get('defaultUnit'));
            }
        }
    }

    public function prepareAttributeData(Entity $attribute, Entity $pav, ?array $classificationAttribute = null): void
    {
        $pav->set('isRequired', $attribute->get('isRequired'));
        $pav->set('maxLength', $attribute->get('maxLength'));
        $pav->set('min', $attribute->get('min'));
        $pav->set('max', $attribute->get('max'));
        $pav->set('countBytesInsteadOfCharacters', $attribute->get('countBytesInsteadOfCharacters'));
        $pav->set('amountOfDigitsAfterComma', $attribute->get('amountOfDigitsAfterComma'));

        if ($classificationAttribute !== null) {
            $pav->set('isRequired', !empty($classificationAttribute['isRequired']));
            $pav->set('maxLength', $classificationAttribute['maxLength'] ?? null);
            $pav->set('countBytesInsteadOfCharacters', $classificationAttribute['countBytesInsteadOfCharacters'] ?? null);
            $pav->set('min', $classificationAttribute['min'] ?? null);
            $pav->set('max', $classificationAttribute['max'] ?? null);
        }
    }

    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($entity->get('productId'))) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions', 'Global'), 'Product'));
        }

        if (empty($entity->get('attributeId'))) {
            throw new BadRequest(sprintf($this->getInjection('language')->translate('fieldIsRequired', 'exceptions', 'Global'), 'Attribute'));
        }

        // for unique index
        if (empty($entity->get('channelId'))) {
            $entity->set('channelId', '');
        }

        if (!$entity->isNew()) {
            $fetched = property_exists($entity, '_fetchedEntity') ? $entity->_fetchedEntity : $this->where(['id' => $entity->get('id')])->findOne(['noCache' => true]);
            $this->getValueConverter()->convertFrom($fetched, $fetched->get('attribute'), false);
            self::$beforeSaveData = $fetched->toArray();
        }

        $attribute = $this->getPavAttribute($entity);
        if (!empty($attribute)) {
            $this->prepareAttributeData($attribute, $entity, $this->findClassificationAttribute($entity));
            $this->validateValue($attribute, $entity);
            $this->populateDefault($entity, $attribute);
        }

        $type = $attribute->get('type');

        /**
         * Rounding float Values using amountOfDigitsAfterComma
         */
        $amountOfDigitsAfterComma = $entity->get('amountOfDigitsAfterComma');
        if ($amountOfDigitsAfterComma !== null) {
            switch ($type) {
                case 'float':
                    if ($entity->get('floatValue') !== null) {
                        $entity->set('floatValue', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('floatValue'), (int)$amountOfDigitsAfterComma));
                        $entity->set('value', $entity->get('floatValue'));
                    }
                    break;
                case 'rangeFloat':
                    if ($entity->get('floatValue') !== null) {
                        $entity->set('floatValue', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('floatValue'), (int)$amountOfDigitsAfterComma));
                        $entity->set('valueFrom', $entity->get('floatValue'));
                    }
                    if ($entity->get('floatValue1') !== null) {
                        $entity->set('floatValue1', $this->roundValueUsingAmountOfDigitsAfterComma((string)$entity->get('floatValue1'), (int)$amountOfDigitsAfterComma));
                        $entity->set('valueTo', $entity->get('floatValue1'));
                    }
                    break;
            }
        }

        /**
         * Check if UNIQUE enabled
         */
        if (!$entity->isNew() && $attribute->get('unique') && $entity->isAttributeChanged('value')) {
            $where = [
                'id!='            => $entity->id,
                'language'        => $entity->get('language'),
                'attributeId'     => $entity->get('attributeId'),
                'channelId'       => $entity->get('channelId'),
                'product.deleted' => false
            ];

            switch ($entity->get('attributeType')) {
                case 'array':
                case 'extensibleMultiEnum':
                    $where['textValue'] = @json_encode($entity->get('textValue'));
                    break;
                case 'text':
                case 'markdown':
                case 'wysiwyg':
                    $where['textValue'] = $entity->get('textValue');
                    break;
                case 'bool':
                    $where['boolValue'] = $entity->get('boolValue');
                    break;
                case 'int':
                    $where['intValue'] = $entity->get('intValue');
                    $where['referenceValue'] = $entity->get('referenceValue');
                    break;
                case 'float':
                    $where['floatValue'] = $entity->get('floatValue');
                    $where['referenceValue'] = $entity->get('referenceValue');
                    break;
                case 'date':
                    $where['dateValue'] = $entity->get('dateValue');
                    break;
                case 'datetime':
                    $where['datetimeValue'] = $entity->get('datetimeValue');
                    break;
                case 'varchar':
                    $where['varcharValue'] = $entity->get('varcharValue');
                    $where['referenceValue'] = $entity->get('referenceValue');
                    break;
                case 'file':
                case 'extensibleEnum':
                case 'link':
                    $where['referenceValue'] = $entity->get('referenceValue');
                    break;
                default:
                    $where['varcharValue'] = $entity->get('varcharValue');
                    break;
            }

            if (!empty($this->select(['id'])->join(['product'])->where($where)->findOne())) {
                throw new BadRequest(sprintf($this->exception("attributeShouldHaveBeUnique"), $attribute->get('name')));
            }
        }

        $this->validateAllowOptions($entity);

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = array())
    {
        $this->updateProductModifiedData($entity);

        parent::afterSave($entity, $options);

        $this->createUpdateNote($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->updateProductModifiedData($entity);

        parent::afterRemove($entity, $options);

        $this->createDeleteNote($entity);
    }

    protected function afterRestore($entity)
    {
        parent::afterRestore($entity);

        $entity->setIsNew(true);
        $this->createUpdateNote($entity);
    }

    public function updateProductModifiedData(Entity $entity): void
    {
        if (property_exists($entity, '_input') && !empty($entity->_input)) {
            if (property_exists($entity->_input, 'isProductUpdate') && !empty($entity->_input->isProductUpdate)) {
                return;
            }
        }

        $this->getPseudoTransactionManager()->pushUpdateEntityJob('Product', $entity->get('productId'), [
            'modifiedAt'   => (new \DateTime())->format('Y-m-d H:i:s'),
            'modifiedById' => $this->getEntityManager()->getUser()->get('id')
        ]);
    }

    /**
     * @param Entity $entity
     * @param string $field
     * @param string $param
     *
     * @return string|null
     */
    protected function getPreparedInheritedField(Entity $entity, string $field, string $param): ?string
    {
        if ($entity->isAttributeChanged($param) && $entity->get($param)) {
            return $field;
        }

        return null;
    }

    public function validateValue(Entity $attribute, Entity $entity): void
    {
        switch ($attribute->get('type')) {
            case 'varchar':
                if (!empty($attribute->get('notNull')) && $entity->get('varcharValue') === null) {
                    $entity->set('varcharValue', '');
                }
                break;

            case 'wysiwyg':
            case 'markdown':
            case 'url':
            case 'text':
                if (!empty($attribute->get('notNull')) && $entity->get('textValue') === null) {
                    $entity->set('textValue', '');
                }
                break;
            case 'bool':
                if (!empty($attribute->get('notNull')) && $entity->get('boolValue') === null) {
                    $entity->set('boolValue', false);
                }
            case 'file':
                if (!empty($attribute->get('fileTypeId')) && !empty($entity->get('referenceValue'))) {
                    $file = $this->getEntityManager()->getRepository('File')->get($entity->get('referenceValue'));
                    if (!empty($file) && $file->get('typeId') !== $attribute->get('fileTypeId')) {
                        throw new BadRequest(sprintf($this->getLanguage()->translate('fileIsInvalid', 'exceptions', 'ProductAttributeValue'), $attribute->get('name')));
                    }
                }
                break;
            case 'int':
                if ($entity->get('min') !== null && $entity->get('intValue') !== null && $entity->get('intValue') < $entity->get('min')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $valueField = $this->getLanguage()->translate('value', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$valueField, $entity->get('min')], $message));
                }
                if ($entity->get('max') !== null && $entity->get('intValue') !== null && $entity->get('intValue') > $entity->get('max')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeLess', 'messages');
                    $valueField = $this->getLanguage()->translate('value', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$valueField, $entity->get('max')], $message));
                }
                if (!empty($attribute->get('notNull')) && $entity->get('intValue') === null) {
                    $entity->set('intValue', 0);
                }
                break;
            case 'float':
                if ($entity->get('min') !== null && $entity->get('floatValue') !== null && $entity->get('floatValue') < $entity->get('min')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $valueField = $this->getLanguage()->translate('value', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$valueField, $entity->get('min')], $message));
                }
                if ($entity->get('max') !== null && $entity->get('floatValue') !== null && $entity->get('floatValue') > $entity->get('max')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeLess', 'messages');
                    $valueField = $this->getLanguage()->translate('value', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$valueField, $entity->get('max')], $message));
                }
                if (!empty($attribute->get('notNull')) && $entity->get('floatValue') === null) {
                    $entity->set('floatValue', 0);
                }
                break;
            case 'extensibleEnum':
                if (empty($this->getMemoryStorage()->get('importJobId'))) {
                    $id = $entity->get('referenceValue');
                    if (!empty($id)) {

                        $option = $this->getEntityManager()
                            ->getConnection()
                            ->createQueryBuilder()
                            ->from('extensible_enum_option')
                            ->select('id')
                            ->where('id IN ( 
                            SELECT extensible_enum_option_id 
                                FROM extensible_enum_extensible_enum_option 
                                WHERE extensible_enum_id=:extensibleEnumId AND deleted=:false
                            )'
                            )
                            ->andWhere('id=:id')
                            ->setParameter(
                                'extensibleEnumId',
                                $attribute->get('extensibleEnumId'),
                                Mapper::getParameterType($attribute->get('extensibleEnumId'))
                            )
                            ->setParameter('false', false, Mapper::getParameterType(false))
                            ->setParameter('id', $id, Mapper::getParameterType($id))
                            ->fetchOne();


                        if (empty($option)) {
                            throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchOptions', 'exceptions'), $id, $attribute->get('name')));
                        }
                    }
                }
                break;
            case 'extensibleMultiEnum':
                if (empty($this->getMemoryStorage()->get('importJobId'))) {
                    $ids = @json_decode((string)$entity->get('textValue'), true);
                    if (!empty($ids)) {
                        $options = $this->getEntityManager()
                            ->getConnection()
                            ->createQueryBuilder()
                            ->from('extensible_enum_option')
                            ->select('id')
                            ->where('id IN ( 
                            SELECT extensible_enum_option_id 
                                FROM extensible_enum_extensible_enum_option 
                                WHERE extensible_enum_id=:extensibleEnumId AND deleted=:false
                            )'
                            )
                            ->andWhere('id IN (:ids)')
                            ->setParameter(
                                'extensibleEnumId',
                                $attribute->get('extensibleEnumId'),
                                Mapper::getParameterType($attribute->get('extensibleEnumId'))
                            )
                            ->setParameter('false', false, Mapper::getParameterType(false))
                            ->setParameter('ids', $ids, Mapper::getParameterType($ids))
                            ->fetchAllAssociative();

                        $diff = array_diff($ids, array_column($options, 'id'));
                        foreach ($diff as $id) {
                            throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchOptions', 'exceptions'), $id, $attribute->get('name')));
                        }
                    }
                }
                break;
            case 'rangeInt':
                if ($entity->get('intValue1') !== null && $entity->get('intValue') !== null && $entity->get('intValue') > $entity->get('intValue1')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $fromLabel = $this->getLanguage()->translate('valueTo', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$attribute->get('name') . ' ' . $fromLabel, $entity->get('intValue')], $message));
                }
                break;
            case 'rangeFloat':
                if ($entity->get('floatValue1') !== null && $entity->get('floatValue') !== null && $entity->get('floatValue') > $entity->get('floatValue1')) {
                    $message = $this->getLanguage()->translate('fieldShouldBeGreater', 'messages');
                    $fromLabel = $this->getLanguage()->translate('valueTo', 'fields', 'ProductAttributeValue');
                    throw new BadRequest(str_replace(['{field}', '{value}'], [$attribute->get('name') . ' ' . $fromLabel, $entity->get('floatValue')], $message));
                }
                break;
        }

        /**
         * Text length validation
         */
        if (in_array($attribute->get('type'), ['varchar', 'text', 'wysiwyg', 'markdown'])) {
            $fieldValue = $attribute->get('type') === 'varchar' ? $entity->get('varcharValue') : $entity->get('textValue');
            if ($fieldValue !== null) {
                $countBytesInsteadOfCharacters = (bool)$entity->get('countBytesInsteadOfCharacters');
                $fieldValue = (string)$fieldValue;
                $length = $countBytesInsteadOfCharacters ? strlen($fieldValue) : mb_strlen($fieldValue);
                $maxLength = (int)$entity->get('maxLength');
                if (!empty($maxLength) && $length > $maxLength) {
                    throw new BadRequest(
                        sprintf($this->getInjection('language')->translate('maxLengthIsExceeded', 'exceptions', 'Global'), $attribute->get('name'), $maxLength, $length)
                    );
                }
            }
        }

        if (in_array($attribute->get('type'), ['rangeInt', 'rangeFloat', 'int', 'float', 'varchar']) && !empty($entity->get('referenceValue'))) {
            $unit = $this->getEntityManager()->getRepository('Unit')
                ->select(['id'])
                ->where([
                    'id'        => $entity->get('referenceValue'),
                    'measureId' => $attribute->get('measureId') ?? 'no-such-measure'
                ])
                ->findOne();

            if (empty($unit)) {
                throw new BadRequest(sprintf($this->getLanguage()->translate('noSuchUnit', 'exceptions', 'Global'), $entity->get('referenceValue'), $attribute->get('name')));
            }
        }
    }

    /**
     * @inheritDoc
     */
    protected function getInheritedEntity(Entity $entity, string $config): ?Entity
    {
        $result = null;

        if ($config == 'fromAttribute') {
            $result = $entity->get('attribute');
        } elseif ($config == 'fromProduct') {
            $result = $entity->get('product');
        }

        return $result;
    }

    public function getValueConverter(): ValueConverter
    {
        return $this->getInjection(ValueConverter::class);
    }

    /**
     * @inheritDoc
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('serviceFactory');
        $this->addDependency('pseudoTransactionManager');
        $this->addDependency(ValueConverter::class);
    }

    /**
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isUnique(Entity $entity): bool
    {
        return empty($this->getDuplicateEntity($entity));
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, 'exceptions', 'ProductAttributeValue');
    }

    protected function createOwnNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function createAssignmentNotification(Entity $entity, ?string $userId): void
    {
    }

    protected function createUpdateNote(Entity $entity): void
    {
        $this->getValueConverter()->convertFrom($entity, $entity->get('attribute'), false);

        $data = $this->getNoteData($entity);
        $type = 'Update';
        if ($entity->isNew()) {
            $type = 'CreatePav';
            $data = [
                'relatedType' => 'Attribute',
                'relatedId'   => $entity->get('attributeId'),
                'pavId'       => $entity->get('id'),
                'locale'      => $entity->get('language') !== 'main' ? $entity->get('language') : '',
                'channelId'   => $entity->get('channelId'),
                'fields'      => $data['fields'] ?? null,
                'attributes'  => $data['attributes'] ?? null
            ];
        }
        if (empty($data)) {
            return;
        }

        $this->getPseudoTransactionManager()->pushCreateEntityJob('Note', [
            'type'       => $type,
            'parentId'   => $entity->get('productId'),
            'parentType' => 'Product',
            'data'       => $data,
        ]);
    }

    protected function createDeleteNote(Entity $entity): void
    {
        $this->getValueConverter()->convertFrom($entity, $entity->get('attribute'), false);

        $data = [
            'relatedType' => 'Attribute',
            'relatedId'   => $entity->get('attributeId'),
            'pavId'       => $entity->get('id'),
            'locale'      => $entity->get('language') !== 'main' ? $entity->get('language') : '',
            'channelId'   => $entity->get('channelId'),
        ];

        $note = $this->getEntityManager()->getEntity('Note');
        $note->set('type', 'DeletePav');
        $note->set('parentId', $entity->get('productId'));
        $note->set('parentType', 'Product');
        $note->set('data', $data);

        $this->getEntityManager()->saveEntity($note);
    }

    protected function notEqualAndNotEmpty($val1, $val2): bool
    {
        return ($val1 !== $val2) && !(empty($val1) && empty($val2));
    }

    protected function getNoteData(Entity $entity): ?array
    {
        if (!property_exists($entity, '_input')) {
            return null;
        }

        $result = [
            'id'     => $entity->get('id'),
            'locale' => $entity->get('language') !== 'main' ? $entity->get('language') : '',
            'fields' => []
        ];

        $wasValue = self::$beforeSaveData['value'] ?? null;
        $wasValueUnitId = self::$beforeSaveData['valueUnitId'] ?? null;
        $input = $entity->_input;

        switch ($entity->get('attributeType')) {
            case 'rangeInt':
                $wasValueFrom = self::$beforeSaveData['valueFrom'] ?? null;
                $wasValueTo = self::$beforeSaveData['valueTo'] ?? null;
                if (property_exists($input, 'intValue') && $wasValueFrom !== $input->intValue) {
                    $result['fields'][] = 'valueFrom';
                    $result['attributes']['was']['valueFrom'] = $wasValueFrom;
                    $result['attributes']['became']['valueFrom'] = $input->intValue;
                }
                if (property_exists($input, 'intValue1') && $wasValueTo !== $input->intValue1) {
                    $result['fields'][] = 'valueTo';
                    $result['attributes']['was']['valueTo'] = $wasValueTo;
                    $result['attributes']['became']['valueTo'] = $input->intValue1;
                }
                if (property_exists($input, 'referenceValue') && $this->notEqualAndNotEmpty($wasValueUnitId, $input->referenceValue)) {
                    $result['fields'][] = 'valueUnit';
                    $result['attributes']['was']['valueUnitId'] = $wasValueUnitId;
                    $result['attributes']['became']['valueUnitId'] = $input->referenceValue;
                }
                break;
            case 'rangeFloat':
                $wasValueFrom = self::$beforeSaveData['valueFrom'] ?? null;
                $wasValueTo = self::$beforeSaveData['valueTo'] ?? null;
                if (property_exists($input, 'floatValue') && !Util::isFloatEquals((float)$wasValueFrom, (float)$input->floatValue1)) {
                    $result['fields'][] = 'valueFrom';
                    $result['attributes']['was']['valueFrom'] = $wasValueFrom;
                    $result['attributes']['became']['valueFrom'] = $input->floatValue;
                }
                if (property_exists($input, 'floatValue1') && !Util::isFloatEquals((float)$wasValueTo, (float)$input->floatValue1)) {
                    $result['fields'][] = 'valueTo';
                    $result['attributes']['was']['valueTo'] = $wasValueTo;
                    $result['attributes']['became']['valueTo'] = $input->floatValue1;
                }
                if (property_exists($input, 'referenceValue') && $this->notEqualAndNotEmpty($wasValueUnitId, $input->referenceValue)) {
                    $result['fields'][] = 'valueUnit';
                    $result['attributes']['was']['valueUnitId'] = $wasValueUnitId;
                    $result['attributes']['became']['valueUnitId'] = $input->referenceValue;
                }
                break;
            case 'int':
                if (property_exists($input, 'intValue') && $wasValue !== $input->intValue) {
                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = $wasValue;
                    $result['attributes']['became']['value'] = $input->intValue;
                }

                if (property_exists($input, 'referenceValue') && $this->notEqualAndNotEmpty($wasValueUnitId, $input->referenceValue)) {
                    $result['fields'][] = 'valueUnit';
                    $result['attributes']['was']['valueUnitId'] = $wasValueUnitId;
                    $result['attributes']['became']['valueUnitId'] = $input->referenceValue;
                }
                break;
            case 'float':
                if (property_exists($input, 'floatValue') && !Util::isFloatEquals((float)$wasValue, (float)$input->floatValue)) {
                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = $wasValue;
                    $result['attributes']['became']['value'] = $input->floatValue;
                }

                if (property_exists($input, 'referenceValue') && $this->notEqualAndNotEmpty($wasValueUnitId, $input->referenceValue)) {
                    $result['fields'][] = 'valueUnit';
                    $result['attributes']['was']['valueUnitId'] = $wasValueUnitId;
                    $result['attributes']['became']['valueUnitId'] = $input->referenceValue;
                }
                break;
            case 'array':
                if ($wasValue !== $entity->get('textValue')) {
                    $becameValue = $entity->get('textValue');
                    $becameValue = (is_array($becameValue) && count($becameValue)) ? $becameValue : null;

                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = (is_array($wasValue) && count($wasValue)) ? $wasValue : null;
                    $result['attributes']['became']['value'] = $becameValue;
                }
                break;
            case 'extensibleEnum':
                if ($wasValue !== $entity->get('value')) {
                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = $wasValue;
                    $result['attributes']['became']['value'] = $entity->get('value');
                }
                break;
            case 'extensibleMultiEnum':
                if ($wasValue !== $entity->get('value')) {
                    $becameValue = $entity->get('value');
                    $becameValue = (is_array($becameValue) && count($becameValue)) ? $becameValue : null;

                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = (is_array($wasValue) && count($wasValue)) ? $wasValue : null;
                    $result['attributes']['became']['value'] = $becameValue;
                }
                break;
            case 'file':
                if ($wasValue !== $entity->get('value')) {
                    $result['fields'][] = 'value';

                    if (!empty($wasValue)) {
                        $wasFile = $this->getEntityManager()->getRepository('File')->get($wasValue);
                    }
                    $result['attributes']['was']['valueId'] = $wasValue;
                    $result['attributes']['was']['valueName'] = !empty($wasFile) ? $wasFile->get('name') : $wasValue;

                    if (!empty($entity->get('valueId'))) {
                        $file = $this->getEntityManager()->getRepository('File')->get($entity->get('valueId'));
                    }
                    $result['attributes']['became']['valueId'] = $entity->get('valueId');
                    $result['attributes']['became']['valueName'] = !empty($file) ? $file->get('name') : $entity->get('valueId');
                }
                break;
            default:
                if ($wasValue !== $entity->get('value')) {
                    $result['fields'][] = 'value';
                    $result['attributes']['was']['value'] = $wasValue;
                    $result['attributes']['became']['value'] = $entity->get('value');
                }
        }

        if (empty($result['fields'])) {
            return [];
        }

        $result['attributeId'] = $entity->get('attributeId');
        $result['pavId'] = $entity->get('id');

        return $result;
    }

    protected function processSpecifiedRelationsSave(Entity $entity, array $options = array())
    {
        parent::processSpecifiedRelationsSave($entity, $options);

        if ($entity->get('attributeType') == 'linkMultiple') {
            self::saveLinkMultipleValues($entity, $this);
        }
    }

    public static function saveLinkMultipleValues(Entity $entity, RDB $repository)
    {
        if ($entity->has('valueIds')) {
            $specifiedIds = $entity->get('valueIds');
            $linkName = $entity->get('attribute')->getLinkMultipleLinkName();
            $existingIds = [];

            $foreignCollection = $entity->get($linkName);
            if (!empty($foreignCollection) && $foreignCollection->count() > 0) {
                foreach ($foreignCollection as $foreignEntity) {
                    $existingIds[] = $foreignEntity->id;
                }
            }

            if (!$entity->isNew()) {
                $entity->setFetched('valueIds', $existingIds);
            }

            foreach ($existingIds as $id) {
                if (!in_array($id, $specifiedIds)) {
                    $repository->unrelate($entity, $linkName, $id);
                }
            }

            foreach ($specifiedIds as $id) {
                if (!in_array($id, $existingIds)) {
                    $repository->relate($entity, $linkName, $id, null);
                }
            }
        }
    }

    public function getClassificationAttributesFromPavId(string|Entity $pavId, ?string $channelId): array
    {
        $pav = is_string($pavId) ? $this->get($pavId) : $pavId;
        $values = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select('distinct ca.id')
            ->from('classification_attribute', 'ca')
            ->join('ca', 'classification', 'c', 'ca.classification_id=c.id and c.deleted=:false')
            ->join('c', 'product_classification', 'pc', 'c.id = pc.classification_id and pc.deleted=:false')
            ->join('pc', 'product', 'p', 'pc.product_id = p.id and p.deleted=:false')
            ->where('ca.classification_id = pc.classification_id')
            ->andWhere('ca.attribute_id = :attributeId')
            ->andWhere('ca.deleted = :false')
            ->andWhere('p.id = :productId')
            ->andWhere('ca.channel_id = :channelId')
            ->setParameter('attributeId', $pav->get('attributeId'))
            ->setParameter('productId', $pav->get('productId'))
            ->setParameter('channelId', $channelId, Mapper::getParameterType($channelId))
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        return array_column($values, 'id');
    }

    protected function validateAllowOptions(Entity $pav): void
    {
        if (!in_array($pav->get('attributeType'), ['extensibleEnum', 'extensibleMultiEnum'])) {
            return;
        }

        if(!$pav->isAttributeChanged('value') && !$pav->isAttributeChanged('channelId')) {
            return;
        }

        if ($pav->isAttributeChanged('value') && (empty($pav->get('referenceValue')) && empty($pav->get('textValue')))) {
            return;
        }

        if ($pav->isAttributeChanged('value')) {
            $data = $pav->get('attributeType') === 'extensibleEnum' ? [$pav->get('referenceValue')] : (@json_decode($pav->get('textValue'), true) ?? []);
        } else {
            $data = $pav->get('attributeType') === 'extensibleEnum' ? [$pav->get('value')] : $pav->get('value');
        }

        $classificationAttributeIds = $this->getClassificationAttributesFromPavId($pav, $pav->get('channelId'));

        if (empty($classificationAttributeIds)) {
            return;
        }

        $key = "list_options_" . $classificationAttributeIds[0];
        $options = $this->getMemoryStorage()->get($key);
        if ($options === null) {
            $options = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('extensible_enum_option_id')
                ->from('classification_attribute_extensible_enum_option')
                ->where('classification_attribute_id IN (:caIds)')
                ->andWhere('deleted = :false')
                ->setParameter('caIds', $classificationAttributeIds, Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();
            $this->getMemoryStorage()->set($key, $options);
        }

        $notAllowOptions = array_diff($data, array_column($options, 'extensible_enum_option_id'));

        if (empty($notAllowOptions)) {
            return;
        }

        if (!empty($this->getMemoryStorage()->get('importJobId'))) {
            $names = $notAllowOptions;
        } else {
            $result = $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->select('name')
                ->from('extensible_enum_option')
                ->where('id IN (:ids)')
                ->andWhere('deleted = :false')
                ->setParameter('ids', $notAllowOptions, Connection::PARAM_STR_ARRAY)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->fetchAllAssociative();

            $names = array_column($result, 'name');
        }

        $exceptionMessage = count($names) > 1 ? 'optionsNotAllowed' : 'optionNotAllowed';

        throw new BadRequest(
            sprintf($this->getInjection('language')->translate($exceptionMessage, 'exceptions', 'ProductAttributeValue'), join(', ', $names))
        );
    }

    protected function getPseudoTransactionManager(): PseudoTransactionManager
    {
        return $this->getInjection('pseudoTransactionManager');
    }
}
