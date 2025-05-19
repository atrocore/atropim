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
}