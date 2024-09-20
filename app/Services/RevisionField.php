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

namespace Pim\Services;

use Atro\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;
use Espo\Entities\Note;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Slim\Http\Request;

class RevisionField extends \Revisions\Services\RevisionField
{
    public function restoreRecordForCreatePav(Note $note): bool
    {
        if (!empty($pavId = $note->get('data')->pavId)) {
            return $this->getInjection('serviceFactory')->create('ProductAttributeValue')->deleteEntity($pavId);
        }
        return true;
    }

    public function restoreRecordForDeletePav(Note $note): bool
    {
        if (!empty($pavId = $note->get('data')->pavId)) {
            return !empty($this->getInjection('serviceFactory')->create('ProductAttributeValue')->restoreEntity($pavId));
        }
        return true;
    }


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
                $data = $this->prepareNoteData($note->get('data'));
                if (!empty($data['pavId']) && $data['pavId'] == $pav->get('id')) {
                    // prepare data
                    foreach ($data['fields'] as $field) {
                        if ($max > count($result['list']) && $result['total'] >= $offset) {
                            $attr = $pav->get('attribute');

                            // prepare data
                            $was = $became = [];
                            if ($attr->get('type') === 'file') {
                                $was['valueId'] = $data['attributes']['was'][$field];
                                $became['valueId'] = $data['attributes']['became'][$field];
                            }

                            $was['value'] = $data['attributes']['was'][$field];
                            $became['value'] = $data['attributes']['became'][$field];

                            if (isset($data['attributes']['was'][$field . 'UnitId'])) {
                                $was['valueUnitId'] = $data['attributes']['was'][$field . 'UnitId'];
                                $became['valueUnitId'] = null;
                            }
                            if (isset($data['attributes']['became'][$field . 'UnitId'])) {
                                if (!isset($was['valueUnitId'])) {
                                    $was['valueUnitId'] = null;
                                }
                                $became['valueUnitId'] = $data['attributes']['became'][$field . 'UnitId'];
                            }

                            if (is_bool($became)) {
                                $was = (bool)$was;
                            }

                            $createdBy = $this->getEntityManager()->getRepository('User')->get($note->get('createdById'));

                            // skip similar
                            if ($was === $became) {
                                continue;
                            }

                            $item = [
                                "id"       => $note->get('id'),
                                "date"     => $note->get('createdAt'),
                                "userId"   => $note->get('createdById'),
                                "userName" => empty($createdBy) ? $note->get('createdById') : $createdBy->get('name'),
                                "was"      => $was,
                                "became"   => $became,
                                "field"    => 'value',
                                "type"     => $attr->get('type')
                            ];

                            if (!empty($attr->get('measureId'))) {
                                $item['measureId'] = $attr->get('measureId');
                            }

                            $result['list'][] = $item;

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
        $data = $note->get('data');
        if ($note->get('type') === 'Update' && !empty($data->pavId)) {
            return $this->getEntityManager()->getEntity('ProductAttributeValue', $data->pavId);
        }

        return parent::getEntity($note);
    }
}
