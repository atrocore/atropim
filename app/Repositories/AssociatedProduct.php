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

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class AssociatedProduct extends Base
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if ($entity->get('mainProductId') == $entity->get('relatedProductId')) {
            throw new BadRequest($this->getInjection('language')->translate('itselfAssociation', 'exceptions', 'Product'));
        }

        if ($entity->isNew() && $entity->get('sorting') === null) {
            $last = $this->where(['mainProductId' => $entity->get('mainProductId')])->order('sorting', 'DESC')->findOne();
            $entity->set('sorting', empty($last) ? 0 : (int)$last->get('sorting') + 10);
        }
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this->createNoteInProduct('CreateProductAssociation', $entity);
        $this->createNoteInAssociation('CreateAssociationProduct', $entity);
    }

    public function removeByProductId(string $productId): void
    {
        $this->where(['mainProductId' => $productId])->removeCollection();
        $this->where(['relatedProductId' => $productId])->removeCollection();
    }

    public function updateSortOrder(array $ids): void
    {
        $collection = $this->where(['id' => $ids])->find();
        if (count($collection) === 0) {
            return;
        }

        foreach ($ids as $k => $id) {
            $sortOrder = (int)$k * 10;
            foreach ($collection as $entity) {
                if ($entity->get('id') !== (string)$id) {
                    continue;
                }
                $entity->set('sorting', $sortOrder);
                $this->save($entity);
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = [])
    {
        parent::beforeRemove($entity, $options);

        /**
         * Delete backward association
         */
        if (empty($options['skipDeleteBackwardAssociatedProduct']) && !empty($backwardAssociatedProduct = $entity->get('backwardAssociatedProduct'))) {
            $this->remove($backwardAssociatedProduct, ['skipDeleteBackwardAssociatedProduct' => true]);
        }
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        parent::afterRemove($entity, $options);

        $this->createNoteInProduct('DeleteProductAssociation', $entity);
        $this->createNoteInAssociation('DeleteAssociationProduct', $entity);
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }

    protected function createNoteInProduct(string $type, Entity $entity): void
    {
        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => $type,
            'parentType' => 'Product',
            'parentId'   => $entity->get('mainProductId'),
            'data'       => [
                'associationId'    => $entity->get('associationId'),
                'relatedProductId' => $entity->get('relatedProductId'),
            ],
        ]);
        $this->getEntityManager()->saveEntity($note);
    }

    protected function createNoteInAssociation(string $type, Entity $entity): void
    {
        $note = $this->getEntityManager()->getEntity('Note');
        $note->set([
            'type'       => $type,
            'parentType' => 'Association',
            'parentId'   => $entity->get('associationId'),
            'data'       => [
                'mainProductId'    => $entity->get('mainProductId'),
                'relatedProductId' => $entity->get('relatedProductId'),
            ],
        ]);
        $this->getEntityManager()->saveEntity($note);
    }
}
