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

namespace Pim\Listeners;

use Atro\Core\AttributeFieldConverter;
use Atro\Core\EventManager\Event;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\KeyValueStorages\MemoryStorage;
use Atro\Services\Record;
use Pim\Services\Attribute;
use Espo\ORM\Entity as OrmEntity;

class Entity extends AbstractEntityListener
{
    public function beforeGetSelectParams(Event $event)
    {
        $params = $event->getArgument('params');
        $entityType = $event->getArgument('entityType');
        if ($entityType === 'ExtensibleEnumOption') {
            if (!empty($params['where'])) {
                foreach ($params['where'] as $key => $filter) {
                    if (!empty($filter['type']) && $filter['type'] === 'bool' && !empty($filter['value'])) {
                        foreach ($filter['value'] as $boolFilter) {
                            $method = "boolFilter" . ucfirst($boolFilter);
                            if (method_exists($this, $method)) {
                                $this->$method($params,
                                    isset($filter['data'][$boolFilter]) ? $filter['data'][$boolFilter] : null);
                            }
                        }
                    }
                }
            }
        }

        $event->setArgument("params", $params);
    }

    protected function boolFilterOnlyExtensibleEnumOptionIds(&$params, $ids)
    {

        if (!is_array($ids) || empty($ids)) {
            return $params;
        }

        $params['where'][] = [
            "type"      => "in",
            "attribute" => "id",
            "value"     => $ids
        ];
        return $params;
    }

    public function beforeSave(Event $event): void
    {
        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        if ($entity->getEntityName() === 'Entity' && $entity->get('hasSingleClassificationOnly') && $entity->get('hasClassification')) {
            $mapper = $this->getEntityManager()->getMapper();
            $entityColumn = $mapper->toDb(lcfirst($entity->get('code')) . 'Id');
            $qb = $this->getEntityManager()->getConnection()->createQueryBuilder();
            $classifications = $qb->select($entityColumn)
                ->from($mapper->toDb($entity->get('code') . 'Classification'), 'ec')
                ->where('ec.deleted = :false')
                ->groupBy($entityColumn)
                ->setParameter('false', false, \Doctrine\DBAL\ParameterType::BOOLEAN)
                ->having('COUNT(*) > 1')
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAllAssociative();

            if (!empty($classifications)) {
                throw new BadRequest($this->getLanguage()->translate('moreThanOneClassification', 'exceptions'));
            }

            return;
        }

        $this->validateClassificationAttributesForRecord($entity);
    }

    public function afterSave(Event $event): void
    {
        /** @var OrmEntity $entity */
        $entity = $event->getArgument('entity');

        // create classification attributes if it needs
        $this->createClassificationAttributesForRecord($entity);
    }

    protected function validateClassificationAttributesForRecord(OrmEntity $entity): void
    {
        $entityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.classificationForEntity");
        if (empty($entityName)) {
            return;
        }

        $classification = $this->getEntityManager()->getRepository('Classification')->get($entity->get('classificationId'));
        if (empty($classification)) {
            throw new NotFound();
        }

        if ($classification->get('entityId') !== $entityName) {
            throw new BadRequest($this->translate('classificationForToAnotherEntity', 'exceptions', 'Classification'));
        }

        if (
            !$this->getMetadata()->get(['scopes', $entityName, 'hasClassification'], false)
            || !$this->getMetadata()->get(['scopes', $entityName, 'hasSingleClassificationOnly'], false)
        ) {
            return;
        }

        if ($entity->isAttributeChanged('classificationsIds') && count($entity->get('classificationsIds')) > 1) {
            throw new BadRequest($this->getLanguage()->translate('singleClassificationOnlyAllowed', 'exceptions'));
        }

        $entityField = lcfirst($entityName) . 'Id';
        if ($entityId = $entity->get($entityField)) {
            $key = 'single_classification_' . $entityName;
            $memoryData = $this->getMemoryStorage()->get($key) ?? [];

            if (empty($memoryData[$entityId])) {
                $this->getService($entityName)->unlinkAll($entityId, 'classifications');
                $memoryData[$entityId] = true;
                $this->getMemoryStorage()->set($key, $memoryData);
            }

            $records = $this->getEntityManager()->getRepository($entity->getEntityName())
                ->where([$entityField => $entityId])
                ->find();

            if (!empty($records[0])) {
                throw new BadRequest($this->getLanguage()->translate('singleClassificationOnlyAllowed', 'exceptions'));
            }
        }
    }

    protected function createClassificationAttributesForRecord(OrmEntity $entity): void
    {
        $entityName = $this->getMetadata()->get("scopes.{$entity->getEntityName()}.classificationForEntity");
        if (empty($entityName)) {
            return;
        }

        $cas = $this->getEntityManager()->getRepository('ClassificationAttribute')
            ->where([
                'classificationId' => $entity->get('classificationId')
            ])
            ->find();

        if (empty($cas[0])) {
            return;
        }

        foreach ($cas as $ca) {
            $data = $ca->get('data')?->default ?? new \stdClass();
            $data = json_decode(json_encode($data), true);
            $data['attributeId'] = $ca->get('attributeId');

            $this->getAttributeService()->createAttributeValue([
                'entityName' => $entityName,
                'entityId'   => $entity->get(lcfirst($entityName) . 'Id'),
                'data'       => $data
            ]);
        }
    }

    protected function getAttributeService(): Attribute
    {
        return $this->getServiceFactory()->create('Attribute');
    }

    protected function getMemoryStorage(): MemoryStorage
    {
        return $this->getContainer()->get('memoryStorage');
    }
}