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

namespace Pim\TwigFunction;

use Espo\Core\Twig\AbstractTwigFunction;
use Espo\ORM\Entity;

class GetProductAttributeValue extends AbstractTwigFunction
{
    public function __construct()
    {
        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
    }

    /**
     * @param string $attributeId
     * @param string $channelId
     * @param string $language
     *
     * @return Entity|null
     */
    public function run(...$input)
    {
        if (empty($input[0])) {
            return null;
        }

        $currentPav = $this->getTemplateData('entity');
        if (empty($currentPav) || $currentPav->getEntityType() !== 'ProductAttributeValue') {
            return null;
        }

        $attributeId = $input[0];
        $channelId = empty($input[1]) ? '' : $input[1];
        $language = empty($input[2]) ? 'main' : $input[2];

        $where = [
            'attributeId' => (string)$attributeId,
            'productId'   => (string)$currentPav->get('productId'),
            'language'    => (string)$language,
            'scope'       => 'Global'
        ];

        if (!empty($channelId)) {
            $where['scope'] = 'Channel';
            $where['channelId'] = $channelId;
        }

        $pav = $this->getInjection('entityManager')->getRepository('ProductAttributeValue')->where($where)->findOne();
        if (!empty($pav)) {
            if ($pav->get('id') === $currentPav->get('id')) {
                return $currentPav;
            }
            $this->getInjection('serviceFactory')->create('ProductAttributeValue')->prepareEntityForOutput($pav);
            return $pav;
        }

        return null;
    }
}
