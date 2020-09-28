<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;
use Espo\Core\Utils\Json;

/**
 * Migration class for version 2.9.8
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot9Dot8 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        $attributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->where([
                'type' => 'text'
            ])
            ->find();

        if (count($attributes) > 0) {
            $notes = $this
                ->getEntityManager()
                ->getRepository('Note')
                ->where([
                    'attributeId' => array_column($attributes->toArray(), 'id')
                ])
                ->find();

            if (count($notes) > 0) {
                foreach ($notes as $note) {
                    if (!empty($note->get('data'))) {
                        $data = Json::decode(Json::encode($note->get('data')), true);

                        foreach ($data['fields'] as $field) {
                            $data['attributes']['was'][$field] = (string)$data['attributes']['was'][$field];
                        }

                        $note->set('data', Json::encode($data));
                        $this->getEntityManager()->saveEntity($note);
                    }
                }
            }
        }
    }
}
