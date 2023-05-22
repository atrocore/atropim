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
