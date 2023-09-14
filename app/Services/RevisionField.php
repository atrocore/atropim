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

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Slim\Http\Request;

class RevisionField extends \Revisions\Services\RevisionField
{
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
            $pav = $this->getEntityManager()->getEntity('ProductAttributeValue', $params['field']);
            if (empty($pav)) {
                throw new Error('Such Attribute Value is not found');
            }

            foreach ($notes as $note) {
                if (!empty($note->get('pavId')) && $note->get('pavId') == $pav->get('id')) {
                    // prepare data
                    $data = $this->prepareNoteData($note->get('data'));

                    foreach ($data['fields'] as $field) {
                        if ($max > count($result['list']) && $result['total'] >= $offset) {
                            $attr = $pav->get('attribute');

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

                            // skip similar
                            if ($was === $became) {
                                continue;
                            }

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

    protected function getEntity(Entity $note): ?Entity
    {
        if (!empty($note->get('pavId'))) {
            return $this->getEntityManager()->getEntity('ProductAttributeValue', $note->get('pavId'));
        }

        return parent::getEntity($note);
    }
}
