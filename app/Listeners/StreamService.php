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

namespace Pim\Listeners;

use Espo\Core\EventManager\Event;

class StreamService extends AbstractEntityListener
{
    public function prepareNoteFieldDefs(Event $event): void
    {
        $entity = $event->getArgument('entity');

        if (empty($entity->get('attributeId'))) {
            return;
        }

        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));

        // for backward compatibility
        if (empty($attribute)) {
            $pav = $this->getEntityManager()->getRepository('ProductAttributeValue')->get($entity->get('attributeId'));
            if (!empty($pav)) {
                $attribute = $pav->get('attribute');
            }
        }

        if (empty($attribute)) {
            return;
        }

        $fieldDefs = [
            'type'  => $attribute->get('type'),
            'label' => $attribute->get('name'),
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
                $fieldDefs['entity'] = 'Asset';
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
