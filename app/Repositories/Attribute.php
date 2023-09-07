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

use Espo\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;
use Pim\AttributeConverters\AttributeConverterInterface;

/**
 * Class Attribute
 */
class Attribute extends AbstractRepository
{
    /**
     * @var string
     */
    protected $ownership = 'fromAttribute';

    /**
     * @var string
     */
    protected $ownershipRelation = 'ProductAttributeValue';

    /**
     * @var string
     */
    protected $assignedUserOwnership = 'assignedUserAttributeOwnership';

    /**
     * @var string
     */
    protected $ownerUserOwnership = 'ownerUserAttributeOwnership';

    /**
     * @var string
     */
    protected $teamsOwnership = 'teamsAttributeOwnership';

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('dataManager');
        $this->addDependency('container');
    }

    public function clearCache(): void
    {
        $this->getInjection('dataManager')->setCacheData('attribute_product_fields', null);
    }

    public function updateSortOrderInAttributeGroup(array $ids): void
    {
        foreach ($ids as $k => $id) {
            $id = $this->getPDO()->quote($id);
            $sortOrder = $k * 10;
            $this->getPDO()->exec("UPDATE `attribute` SET sort_order_in_attribute_group=$sortOrder WHERE id=$id");
        }
    }

    public function getMultilingualAttributeTypes(): array
    {
        $attributes = [];
        foreach ($this->getMetadata()->get(['attributes'], []) as $attribute => $attributeDefs) {
            if (!empty($attributeDefs['multilingual'])) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if ($entity->get('code') === '') {
            $entity->set('code', null);
        }

        if (!in_array($entity->get('type'), $this->getMultilingualAttributeTypes())) {
            $entity->set('isMultilang', false);
        }

        if ($entity->get('sortOrderInProduct') === null) {
            $entity->set('sortOrderInProduct', time());
        }

        if ($entity->get('sortOrderInAttributeGroup') === null) {
            $entity->set('sortOrderInAttributeGroup', time());
        }


        if (!$entity->isNew() && $entity->isAttributeChanged('unique') && $entity->get('unique')) {
            $query = "SELECT COUNT(*) 
                      FROM product_attribute_value 
                      WHERE attribute_id='{$entity->id}' 
                        AND deleted=0 %s 
                      GROUP BY %s, `language`, scope, channel_id HAVING COUNT(*) > 1";
            switch ($entity->get('type')) {
                case 'currency':
                    $query = sprintf($query, 'AND float_value IS NOT NULL AND varchar_value IS NOT NULL', 'float_value, varchar_value');
                    break;
                case 'float':
                    $query = sprintf($query, 'AND float_value IS NOT NULL', 'float_value');
                    break;
                case 'int':
                    $query = sprintf($query, 'AND int_value IS NOT NULL', 'int_value');
                case 'date':
                    $query = sprintf($query, 'AND date_value IS NOT NULL', 'date_value');
                case 'datetime':
                    $query = sprintf($query, 'AND datetime_value IS NOT NULL', 'datetime_value');
                    break;
                default:
                    $query = sprintf($query, 'AND varchar_value IS NOT NULL', 'varchar_value');
                    break;
            }

            if (!empty($this->getPDO()->query($query)->fetch(\PDO::FETCH_ASSOC))) {
                throw new Error($this->exception('attributeNotHaveUniqueValue'));
            }
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('pattern') && !empty($pattern = $entity->get('pattern'))) {
            if (!preg_match("/^\/(.*)\/$/", $pattern)) {
                throw new BadRequest($this->getInjection('language')->translate('regexNotValid', 'exceptions', 'FieldManager'));
            }

            $query = "SELECT DISTINCT varchar_value
                      FROM product_attribute_value 
                      WHERE deleted=0 
                        AND attribute_id='{$entity->get('id')}'
                        AND varchar_value IS NOT NULL 
                        AND varchar_value!=''";

            foreach ($this->getPDO()->query($query)->fetchAll(\PDO::FETCH_COLUMN) as $value) {
                if (!preg_match($pattern, $value)) {
                    throw new BadRequest($this->exception('someAttributeDontMathToPattern'));
                }
            }
        }

        parent::beforeSave($entity, $options);

        $this->validateMinMax($entity);
    }

    public function validateMinMax(Entity $entity): void
    {
        if (
            ($entity->isAttributeChanged('max') || $entity->isAttributeChanged('min'))
            && $entity->get('min') !== null
            && $entity->get('max') !== null
            && $entity->get('max') < $entity->get('min')
        ) {
            throw new BadRequest($this->getInjection('language')->translate('maxLessThanMin', 'exceptions', 'Attribute'));
        }
    }

    public function save(Entity $entity, array $options = [])
    {
        if (!$this->getPDO()->inTransaction()) {
            $this->getPDO()->beginTransaction();
            $inTransaction = true;
        }

        try {
            if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
                $converterName = $this->getMetadata()->get(['attributes', $entity->getFetched('type'), 'convert', $entity->get('type')]);
                if (empty($converterName)) {
                    $message = $this->getInjection('language')->translate('noAttributeConverterFound', 'exceptions', 'Attribute');
                    throw new BadRequest(sprintf($message, $entity->getFetched('type'), $entity->get('type')));
                }
                $this->getInjection('container')->get($converterName)->convert($entity);
            }

            if ($entity->isAttributeChanged('measureId') && empty($entity->get('measureId'))) {
                $this->getPDO()->exec("UPDATE product_attribute_value SET varchar_value=NULL WHERE attribute_id='{$entity->get('id')}'");
            }

            $result = parent::save($entity, $options);
            if (!empty($inTransaction)) {
                $this->getPDO()->commit();
            }
        } catch (\Throwable $e) {
            if (!empty($inTransaction)) {
                $this->getPDO()->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    protected function afterSave(Entity $entity, array $options = array())
    {
        if ($entity->isAttributeChanged('virtualProductField') || (!empty($entity->get('virtualProductField') && $entity->isAttributeChanged('code')))) {
            $this->clearCache();
        }

        parent::afterSave($entity, $options);

        /**
         * Delete all lingual product attribute values
         */
        if (!$entity->isNew() && $entity->isAttributeChanged('isMultilang') && empty($entity->get('isMultilang'))) {
            while (true) {
                $pavs = $this->getEntityManager()->getRepository('ProductAttributeValue')
                    ->where(['attributeId' => $entity->get('id'), 'language!=' => 'main'])
                    ->limit(0, 2000)
                    ->order('createdAt', 'ASC')
                    ->find();
                if (empty($pavs[0])) {
                    break;
                }
                foreach ($pavs as $pav) {
                    $this->getEntityManager()->removeEntity($pav);
                }
            }
        }

        $this->setInheritedOwnership($entity);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($entity->get('virtualProductField'))) {
            $this->clearCache();
        }

        parent::afterRemove($entity, $options);
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->getInjection('language')->translate($key, "exceptions", "Attribute");
    }
}
