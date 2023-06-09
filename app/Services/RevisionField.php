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

use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Util;
use Revisions\Services\RevisionField as MultilangRevisionField;
use Espo\ORM\EntityCollection;
use Slim\Http\Request;

/**
 * RevisionField service
 */
class RevisionField extends MultilangRevisionField
{
    /**
     * Prepare data
     *
     * @param array            $params
     * @param EntityCollection $notes
     * @param Request          $request
     *
     * @return array
     */
    protected function prepareData(array $params, EntityCollection $notes, Request $request): array
    {
        if (!empty($request->get('isAttribute'))) {
            // prepare result
            $result = [
                'total' => 0,
                'list'  => []
            ];

            // prepare params
            $max = (int)$request->get('maxSize');
            $offset = (int)$request->get('offset');
            if (empty($max)) {
                $max = $this->maxSize;
            }
            $attribute = $this->getEntityManager()->getEntity('ProductAttributeValue', $params['field']);
            if (empty($attribute)) {
                throw new Error('Such Attribute Value is not found');
            }

            foreach ($notes as $note) {
                if (!empty($note->get('attributeId')) && $note->get('attributeId') == $attribute->id) {
                    // prepare data
                    $data = $this->prepareNoteData($note->get('data'));

                    foreach ($data['fields'] as $field) {
                        if ($max > count($result['list']) && $result['total'] >= $offset) {
                            $attr = $attribute->get('attribute');

                            // prepare data
                            $was = $became = [];
                            if ($attr->get('type') === 'asset') {
                                $was['valueId'] = $data['attributes']['was'][$field];
                                $became['valueId'] = $data['attributes']['became'][$field];
                            }

                            $was['value'] = $data['attributes']['was'][$field];
                            $became['value'] = $data['attributes']['became'][$field];

                            if (isset($data['attributes']['was'][$field . 'UnitId'])) {
                                $was['valueUnitId'] = $data['attributes']['was'][$field . 'Unit'];
                                $became['valueUnitId'] = null;
                            }
                            if (isset($data['attributes']['became'][$field . 'UnitId'])) {
                                if (!isset($was['valueUnitId'])) {
                                    $was['valueUnitId'] = null;
                                }
                                $became['valueUnitId'] = $data['attributes']['became'][$field . 'UnitId'];
                            }

                            // for currency
                            if (isset($data['attributes']['was'][$field . 'Currency'])) {
                                $was['valueCurrency'] = $data['attributes']['was'][$field . 'Currency'];
                                $became['valueCurrency'] = null;
                            }
                            if (isset($data['attributes']['became'][$field . 'Currency'])) {
                                if (!isset($was['valueCurrency'])) {
                                    $was['valueCurrency'] = null;
                                }
                                $became['valueCurrency'] = $data['attributes']['became'][$field . 'Currency'];
                            }

                            if (is_bool($became)) {
                                $was = (bool)$was;
                            }
                            
                            $createdBy = $this->getEntityManager()->getRepository('User')->get($note->get('createdById'));

                            $result['list'][] = [
                                "id"       => $note->get('id'),
                                "date"     => $note->get('createdAt'),
                                "userId"   => $note->get('createdById'),
                                "userName" => empty($createdBy) ? $note->get('createdById') : $createdBy->get('name'),
                                "was"      => $was,
                                "became"   => $became,
                                "field"    => 'value'
                            ];

                            $result['total'] = $result['total'] + 1;
                        }
                    }
                }
            }
        } else {
            $result = parent::prepareData($params, $notes, $request);
        }

        return $result;
    }
}
