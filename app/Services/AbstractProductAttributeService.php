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

namespace Pim\Services;

use Atro\Core\Templates\Services\Base;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

class AbstractProductAttributeService extends Base
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
