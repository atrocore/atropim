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

use Espo\Core\Utils\Util;
use Revisions\Services\RevisionField as MultilangRevisionField;
use Espo\ORM\EntityCollection;
use Espo\Core\Utils\Json;
use Slim\Http\Request;

/**
 * RevisionField service
 *
 * @author r.ratsun <rr@atrocore.com>
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
            $isImageAttr = $this->checkIsAttributeImage($params['field']);
            foreach ($notes as $note) {
                if (!empty($note->get('attributeId')) && $note->get('attributeId') == $params['field']) {
                    // prepare data
                    $data = Json::decode(Json::encode($note->get('data')), true);

                    foreach ($data['fields'] as $field) {
                        if ($max > count($result['list']) && $result['total'] >= $offset) {
                            // prepare field name
                            $fieldName = 'value';

                            // prepare data
                            $was = $became = [];
                            if ($isImageAttr) {
                                $was[$fieldName . 'Id'] = $data['attributes']['was'][$field];
                                $became[$fieldName . 'Id'] = $data['attributes']['became'][$field];
                            }
                            $was[$fieldName] = $data['attributes']['was'][$field];
                            $became[$fieldName] = $data['attributes']['became'][$field];

                            if (isset($data['attributes']['was'][$field . 'Unit'])
                                && isset($data['attributes']['became'][$field . 'Unit'])) {
                                $was[$fieldName . 'Unit'] = $data['attributes']['was'][$field . 'Unit'];
                                $became[$fieldName . 'Unit'] = $data['attributes']['became'][$field . 'Unit'];
                            }

                            if (is_bool($became)) {
                                $was = (bool)$was;
                            }

                            $result['list'][] = [
                                "id"       => $note->get('id'),
                                "date"     => $note->get('createdAt'),
                                "userId"   => $note->get('createdById'),
                                "userName" => $note->get('createdBy')->get('name'),
                                "was"      => $was,
                                "became"   => $became,
                                "field"    => $fieldName
                            ];
                        }
                        $result['total'] = $result['total'] + 1;
                    }
                }
            }
        } else {
            $result = parent::prepareData($params, $notes, $request);
        }

        return $result;
    }

    /**
     * @param $id
     *
     * @return bool
     * @throws Error
     */
    private function checkIsAttributeImage($id): bool
    {
        $attrValue = $this
            ->getEntityManager()
            ->getEntity('ProductAttributeValue', $id);

        return !empty($attrValue) && $attrValue->get('attribute')->get('type') === 'image';
    }
}
