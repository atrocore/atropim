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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

/**
 * Class ProductFamilyAttribute
 */
class ProductFamilyAttribute extends Base
{
    /**
     * @var array
     */
    protected $mandatorySelectAttributeList = ['scope', 'isRequired'];

    /**
     * @inheritDoc
     */
    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if (!empty($attribute = $entity->get('attribute'))) {
            $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
            $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
            $entity->set('sortOrder', $attribute->get('sortOrder'));
            if (!empty($this->getConfig()->get('isMultilangActive'))) {
                foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                    $preparedLocale = ucfirst(Util::toCamelCase(strtolower($locale)));
                    $entity->set('attributeName' . $preparedLocale, $attribute->get('name' . $preparedLocale));
                }
            }
        }
    }

    public function createEntity($attachment)
    {
        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $result = parent::createEntity($attachment);
            $this->createPseudoTransactionCreateJobs(clone $attachment);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionCreateJobs(\stdClass $data): void
    {
        if (!property_exists($data, 'productFamilyId')) {
            return;
        }

        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->select(['id', 'name'])
            ->where(['productFamilyId' => $data->productFamilyId])
            ->find();

        foreach ($products as $product) {
            $inputData = clone $data;
            $inputData->productId = $product->get('id');
            $inputData->productName = $product->get('name');
            unset($inputData->productFamilyId);

            $this->getPseudoTransactionManager()->pushCreateEntityJob('ProductAttributeValue', $inputData);
        }
    }

    public function updateEntity($id, $data)
    {
        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $this->createPseudoTransactionUpdateJobs($id, clone $data);
            $result = parent::updateEntity($id, $data);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionUpdateJobs(string $id, \stdClass $data): void
    {
        foreach ($this->getRepository()->getInheritedPavsIds($id) as $pavId) {
            $inputData = new \stdClass();
            foreach (['scope', 'channelId', 'isRequired'] as $key) {
                if (property_exists($data, $key)) {
                    $inputData->$key = $data->$key;
                }
            }

            if (!empty((array)$inputData)) {
                $this->getPseudoTransactionManager()->pushUpdateEntityJob('ProductAttributeValue', $pavId, $inputData);
            }
        }
    }

    public function deleteEntity($id)
    {
        $this->getEntityManager()->getPDO()->beginTransaction();
        try {
            $this->createPseudoTransactionDeleteJobs($id);
            $result = parent::deleteEntity($id);
            $this->getEntityManager()->getPDO()->commit();
        } catch (\Throwable $e) {
            $this->getEntityManager()->getPDO()->rollBack();
            throw $e;
        }

        return $result;
    }

    protected function createPseudoTransactionDeleteJobs(string $id): void
    {
        foreach ($this->getRepository()->getInheritedPavsIds($id) as $pavId) {
            $this->getPseudoTransactionManager()->pushDeleteEntityJob('ProductAttributeValue', $pavId);
        }
    }
}
