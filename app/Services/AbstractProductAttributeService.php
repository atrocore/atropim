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
use Espo\ORM\Entity;

class AbstractProductAttributeService extends \Espo\Core\Templates\Services\Relationship
{
    protected function prepareDefaultLanguages(\stdClass $attachment): void
    {
        if (
            !property_exists($attachment, 'language')
            && !property_exists($attachment, 'languages')
            && property_exists($attachment, 'attributeId')
            && !empty($attribute = $this->getEntityManager()->getEntity('Attribute', $attachment->attributeId))
            && $attribute->get('isMultilang')
        ) {
            $attachment->languages = array_merge($this->getConfig()->get('inputLanguageList', []), ['main']);
        }
    }

    protected function multipleCreateViaLanguages(\stdClass $attachment)
    {
        if (property_exists($attachment, 'channelId') && !empty($channel = $this->getEntityManager()->getEntity('Channel', $attachment->channelId))) {
            if (!empty($channel->get('locales'))) {
                $attachment->languages = array_intersect($attachment->languages, $channel->get('locales'));
            }
        }

        foreach ($attachment->languages as $language) {
            $attach = clone $attachment;
            unset($attach->languages);
            $attach->language = $language;

            try {
                $entity = $this->createEntity($attach);
                $result = $entity;
            } catch (\Throwable $e) {
                $GLOBALS['log']->error('MultipleCreateViaLanguages: ' . $e->getMessage());
            }
        }

        if (empty($result)) {
            throw $e;
        }

        return $result;
    }

    protected function getAttributeViaInputData(\stdClass $data, ?string $id = null): Entity
    {
        if (property_exists($data, 'attributeId')) {
            $attribute = $this->getEntityManager()->getRepository('Attribute')->get($data->attributeId);
        } elseif (!empty($id) && !empty($entity = $this->getRepository()->get($id))) {
            $attribute = $entity->get('attribute');
        }

        if (empty($attribute)) {
            throw new BadRequest('Attribute is required.');
        }

        return $attribute;
    }
}
