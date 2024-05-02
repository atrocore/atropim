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

use Atro\Core\Templates\Repositories\Base;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;

abstract  class AbstractAttributeValue extends Base
{
    protected array $recordPavs = [
        "Product" => [],
        "Classification" =>  []
    ];
    public function getChildrenArray(string $parentId, bool $withChildrenCount = true, int $offset = null, $maxSize = null, $selectParams = null): array
    {
        if($this->entityType === 'ProductAttributeValue'){
            $foreignEntity = "Product";
            $foreignFieldId = "productId";
        } else {
            $foreignEntity = "Classification";
            $foreignFieldId = "classificationId";
        }
        $pav = $this->get($parentId);
        if (empty($pav) || empty($pav->get($foreignFieldId))) {
            return [];
        }

        $records = $this->getEntityManager()->getRepository($foreignEntity)->getChildrenArray($pav->get($foreignFieldId));

        if (empty($records)) {
            return [];
        }

        $qb = $this->getConnection()->createQueryBuilder()
            ->select('pav.*')
            ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore($this->entityType)), 'pav')
            ->where('pav.deleted = :false')
            ->andWhere('pav.'.Util::toUnderScore($foreignFieldId).' IN (:recordsIds)')
            ->andWhere('pav.attribute_id = :attributeId')
            ->andWhere('pav.language = :language')
            ->andWhere('pav.channel_id = :channelId')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('recordsIds', array_column($records, 'id'), Connection::PARAM_STR_ARRAY)
            ->setParameter('attributeId', $pav->get('attributeId'))
            ->setParameter('language', $pav->get('language'))
            ->setParameter('channelId', $pav->get('channelId'));

        $pavs = $qb->fetchAllAssociative();

        $result = [];
        foreach ($pavs as $pav) {
            foreach ($records as $record) {
                if ($record['id'] === $pav['product_id']) {
                    $pav['childrenCount'] = $record['childrenCount'];
                    break 1;
                }
            }
            $result[] = $pav;
        }

        return $result;
    }

    public function isPavRelationInherited(Entity $entity): bool
    {
        return !empty($this->getParentPav($entity));
    }

    public function isPavValueInherited(Entity $entity): ?bool
    {
        $pavs = $this->getParentsPavs($entity);
        if ($pavs === null) {
            return null;
        }

        foreach ($pavs as $pav) {
            if (
                $pav->get('attributeId') === $entity->get('attributeId')
                && $pav->get('channelId') === $entity->get('channelId')
                && $pav->get('language') === $entity->get('language')
                && ($this->entityType !== 'ClassificationAttribute' || ($pav->get('isRequired') === $entity->get('isRequired')))
                && $this->arePavsValuesEqual($pav, $entity)
            ) {
                return true;
            }
        }

        return false;
    }

    public function arePavsValuesEqual(Entity $pav1, Entity $pav2): bool
    {
        $attribute = $pav1->get('attribute');
        switch ($attribute->get('type')) {
            case 'array':
            case 'extensibleMultiEnum':
                $val1 = @json_decode((string)$pav1->get('textValue'), true);
                $val2 = @json_decode((string)$pav2->get('textValue'), true);
                $result = Entity::areValuesEqual(Entity::TEXT, json_encode($val1 ?? []), json_encode($val2 ?? []));
                break;
            case 'text':
            case 'wysiwyg':
                $result = Entity::areValuesEqual(Entity::TEXT, $pav1->get('textValue'), $pav2->get('textValue'));
                break;
            case 'bool':
                $result = Entity::areValuesEqual(Entity::BOOL, $pav1->get('boolValue'), $pav2->get('boolValue'));
                break;
            case 'int':
                $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue'), $pav2->get('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                }
                break;
            case 'rangeInt':
                $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue'), $pav2->get('intValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::INT, $pav1->get('intValue1'), $pav2->get('intValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                }
                break;
            case 'float':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                }
                break;
            case 'rangeFloat':
                $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue'), $pav2->get('floatValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::FLOAT, $pav1->get('floatValue1'), $pav2->get('floatValue1'));
                }
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                }
                break;
            case 'date':
                $result = Entity::areValuesEqual(Entity::DATE, $pav1->get('dateValue'), $pav2->get('dateValue'));
                break;
            case 'datetime':
                $result = Entity::areValuesEqual(Entity::DATETIME, $pav1->get('datetimeValue'), $pav2->get('datetimeValue'));
                break;
            case 'file':
            case 'link':
            case 'extensibleEnum':
                $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                break;
            case 'varchar':
                $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                if ($result) {
                    $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('referenceValue'), $pav2->get('referenceValue'));
                }
                break;
            default:
                $result = Entity::areValuesEqual(Entity::VARCHAR, $pav1->get('varcharValue'), $pav2->get('varcharValue'));
                break;
        }

        return $result;
    }

    public function getParentsPavs(Entity $entity): ?EntityCollection
    {
        if($this->entityType === 'ProductAttributeValue'){
            $foreignEntity = "Product";
            $foreignFieldId = "productId";

        } else {
            $foreignEntity = "Classification";
            $foreignFieldId = "classificationId";
        }

        if (isset($this->recordPavs[$foreignEntity][$entity->get($foreignFieldId)])) {
            return $this->recordPavs[$foreignEntity][$entity->get($foreignFieldId)];
        }

        $hierarchyTable = strtolower($foreignEntity).'_hierarchy';
        $foreignColumn = Util::toUnderScore($foreignFieldId);
        $res = $this->getConnection()->createQueryBuilder()
            ->select('pav.id')
            ->from($this->getConnection()->quoteIdentifier(Util::toUnderScore($this->entityType)), 'pav')
            ->where(
                "pav.$foreignColumn IN (SELECT ph.parent_id FROM {$this->getConnection()->quoteIdentifier($hierarchyTable)} ph WHERE ph.deleted = :false AND ph.entity_id = :recordId)"
            )
            ->andWhere('pav.deleted = :false')
            ->setParameter('false', false, Mapper::getParameterType(false))
            ->setParameter('recordId', $entity->get($foreignFieldId), Mapper::getParameterType($entity->get($foreignFieldId)))
            ->fetchAllAssociative();

        $ids = array_column($res, 'id');

        $this->recordPavs[$foreignEntity][$entity->get($foreignFieldId)] = empty($ids) ? null : $this->where(['id' => $ids])->find();

        return $this->recordPavs[$foreignEntity][$entity->get($foreignFieldId)];
    }

    public function getParentPav(Entity $entity): ?Entity
    {
        $pavs = $this->getParentsPavs($entity);
        if ($pavs === null) {
            return null;
        }

        foreach ($pavs as $pav) {
            if (
                $pav->get('attributeId') === $entity->get('attributeId')
                && ((empty($pav->get('channelId')) && empty($entity->get('channelId'))) ||
                    $pav->get('channelId') === $entity->get('channelId'))
                && $pav->get('language') === $entity->get('language')
            ) {
                return $pav;
            }
        }
        return null;
    }

}