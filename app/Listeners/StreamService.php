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

use Atro\Core\EventManager\Event;
use Espo\ORM\EntityCollection;
use Espo\ORM\IEntity;
use Pim\Entities\Attribute;

class StreamService extends AbstractEntityListener
{
    public function getSelectParams(Event $event): void
    {
        $params = $event->getArgument('params');
        $selectParams = $event->getArgument('selectParams');

        if (!empty($params['filter']) && $params['filter'] === 'updates') {
            $selectParams['whereClause']['type'] = array_merge($selectParams['whereClause']['type'], [
                'CreatePav',
                'DeletePav',
            ]);
            $event->setArgument('selectParams', $selectParams);
        }
    }

    public function prepareCollectionForOutput(Event $event): void
    {
        /** @var EntityCollection $collection */
        $collection = $event->getArgument('collection');

        $associations = [];
        $products = [];

        foreach ($collection as $entity) {
            if (empty($entity->get('data'))) {
                continue;
            }

            if (!empty($entity->get('data')->associationId)) {
                $associations[$entity->get('data')->associationId] = null;
            }
            if (!empty($entity->get('data')->mainProductId)) {
                $products[$entity->get('data')->mainProductId] = null;
            }
            if (!empty($entity->get('data')->relatedProductId)) {
                $products[$entity->get('data')->relatedProductId] = null;
            }
        }

        foreach ($this->getEntityManager()->getRepository('Association')->where(['id' => array_keys($associations)])->find() as $association) {
            $associations[$association->get('id')] = $association;
        }

        foreach ($this->getEntityManager()->getRepository('Product')->where(['id' => array_keys($products)])->find() as $product) {
            $products[$product->get('id')] = $product;
        }

        foreach ($collection as $entity) {
            if (empty($entity->get('data'))) {
                continue;
            }
            if (!empty($entity->get('data')->associationId) && !empty($associations[$entity->get('data')->associationId])) {
                $entity->get('data')->associationName = $associations[$entity->get('data')->associationId]->get('name');
            }
            if (!empty($entity->get('data')->mainProductId) && !empty($products[$entity->get('data')->mainProductId])) {
                $entity->get('data')->mainProductName = $products[$entity->get('data')->mainProductId]->get('name');
            }
            if (!empty($entity->get('data')->relatedProductId) && !empty($products[$entity->get('data')->relatedProductId])) {
                $entity->get('data')->relatedProductName = $products[$entity->get('data')->relatedProductId]->get('name');
            }

            if (in_array($entity->get('type'), ['DeletePav', 'CreatePav'])) {
                $language = $this->getNoteLanguage($entity);
                if (!empty($language)) {
                    $entity->set('language', $language);
                }
            }
        }
    }

    public function getNoteLanguage(IEntity $entity): ?string
    {
        $languages = $this->getConfig()->get('referenceData.Language');
        $attribute = $this->getAttribute($entity);
        if (!empty($attribute) && !empty($attribute->get('isMultilang'))) {
            $language = $entity->get('data')->locale ?? null;
            if (empty($language) || $language == 'main') {
                $language = $this->getConfig()->get('mainLanguage');
            }
            if (!empty($languages[$language])) {
                $language = $languages[$language]['name'];
            }
            return $language;
        }
        return null;
    }

    public function getAttribute(IEntity $note): ?Attribute
    {
        $attributeId = $note->get('data')->attributeId ?? ($note->get('data')->relatedId ?? null);

        if (empty($attributeId)) {
            return null;
        }

        return $this->getEntityManager()->getRepository('Attribute')->get($attributeId);
    }

    public function prepareNoteFieldDefs(Event $event): void
    {
        $entity = $event->getArgument('entity');

        $attribute = $this->getAttribute($entity);

        if (empty($attribute)) {
            return;
        }

        $language = $this->getNoteLanguage($entity);
        $label = $attribute->get('name');

        if (!empty($language)) {
            $label = $label . ' / ' . $language;
        }

        $fieldDefs = [
            'type'  => $attribute->get('type'),
            'label' => $label,
        ];
        if (!empty($attribute->get('extensibleEnumId'))) {
            $fieldDefs['extensibleEnumId'] = $attribute->get('extensibleEnumId');
        }

        switch ($event->getArgument('field')) {
            case 'valueFrom':
                $fieldDefs['type'] = 'float';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('From');
                break;
            case 'valueTo':
                $fieldDefs['type'] = 'float';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('To');
                break;
            case 'valueId':
                $fieldDefs['type'] = 'link';
                $fieldDefs['entity'] = 'File';
                break;
            case 'valueUnit':
                $fieldDefs['type'] = 'link';
                $fieldDefs['entity'] = 'Unit';
                $fieldDefs['label'] .= ' ' . $this->getLanguage()->translate('unitPart');
                break;
        }

        $event->setArgument('fieldDefs', $fieldDefs);
    }
}
