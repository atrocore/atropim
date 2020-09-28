<?php

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;
use Espo\Core\Utils\Json;

/**
 * Migration class for version 2.2.4
 *
 * @author r.ratsun@gmail.com
 */
class V2Dot2Dot4 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        $this->parseArrayMultiLangNoteData();
    }

    /**
     * Parse arrayMultiLang values in Note entity
     */
    protected function parseArrayMultiLangNoteData(): void
    {
        $attributes = $this
            ->getEntityManager()
            ->getRepository('Attribute')
            ->select(['id'])
            ->where([
                'type' => 'arrayMultiLang'
            ])
            ->find()
            ->toArray();

        if (!empty($attributes)) {
            $attributes = array_column($attributes, 'id');

            $notes = $this
                ->getEntityManager()
                ->getRepository('Note')
                ->select(['id', 'data'])
                ->where([
                    'attributeId' => $attributes
                ])
                ->find();

            if (count($notes) > 0) {
                $sql = '';
                foreach ($notes as $note) {
                    $data = Json::decode(Json::encode($note->get('data')), true);

                    foreach ($data['attributes']['was'] as $key => $value) {
                        if (!empty($value)) {
                            $data['attributes']['was'][$key] = Json::decode($value, true);
                        }
                    }

                    $sql .= sprintf(
                        "UPDATE note SET data='%s' WHERE id='%s' AND deleted=0;",
                        Json::encode($data),
                        $note->get('id')
                    );
                }

                if (!empty($sql)) {
                    $sth = $this
                        ->getEntityManager()
                        ->getPDO()
                        ->prepare($sql);
                    $sth->execute();
                }
            }
        }
    }
}
