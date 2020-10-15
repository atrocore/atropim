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

/**
 * Class ProductsByTagDashlet
 */
class ProductsByTagDashlet extends AbstractProductDashletService
{
    /**
     * Int Class
     */
    public function init()
    {
        parent::init();

        $this->addDependency('metadata');
    }

    /**
     * Get Product types
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getDashlet(): array
    {
        $result = ['total' => 0, 'list' => []];

        // get tags
        $tags = $this->getInjection('metadata')->get('entityDefs.Product.fields.tag.options');
        $tags = is_array($tags) ? $tags : [];

        $result['total'] = count($tags);
        // prepare data
        foreach ($tags as $tag) {
            $where = [
                'tag*' => "%\"$tag\"%",
                'type' => $this->getProductTypes()
            ];

            $result['list'][] = [
                'id'     => $tag,
                'name'   => $tag,
                'amount' => $this->getRepository('Product')->where($where)->count()
            ];
        }

        return $result;
    }
}
