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

use Espo\ORM\Entity;
use Espo\Core\Templates\Services\Hierarchy;

class Classification extends Hierarchy
{
    /**
     * @param Entity $entity
     * @param Entity $duplicatingEntity
     */
    protected function duplicateClassificationAttributes(Entity $entity, Entity $duplicatingEntity)
    {
        if (!empty($classificationAttributes = $duplicatingEntity->get('classificationAttributes')->toArray())) {
            // get service
            $service = $this->getInjection('serviceFactory')->create('ClassificationAttribute');

            foreach ($classificationAttributes as $classificationAttribute) {
                // prepare data
                $data = $service->getDuplicateAttributes($classificationAttribute['id']);
                $data->classificationId = $entity->get('id');

                // create entity
                $service->createEntity($data);
            }
        }
    }

    protected function getFieldsThatConflict(Entity $entity, \stdClass $data): array
    {
        return [];
    }

    protected function isEntityUpdated(Entity $entity, \stdClass $data): bool
    {
        return true;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('serviceFactory');
    }
}
