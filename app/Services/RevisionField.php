<?php

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
 * @author r.ratsun <r.ratsun@gmail.com>
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
