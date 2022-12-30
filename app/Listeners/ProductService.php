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


namespace Pim\Listeners;

use Espo\Core\EventManager\Event;
use Espo\Listeners\AbstractListener;

class ProductService extends AbstractListener
{
    public function inheritAllForChild(Event $event): void
    {
        $parent = $event->getArgument('parent');
        $child = $event->getArgument('child');

        $pavs = $parent->get('productAttributeValues');
        if (!empty($pavs[0])) {
            $pavRepository = $this->getEntityManager()->getRepository('ProductAttributeValue');
            $pavService = $this->getService('ProductAttributeValue');
            foreach ($pavs as $parentPav) {
                $childPav = $pavRepository->getChildPavForProduct($parentPav, $child);

                // create child PAV if not exist
                if (empty($childPav)) {
                    $childPav = $pavRepository->get();
                    $childPav->set($parentPav->toArray());
                    $childPav->id = null;
                    $childPav->set('productId', $child->get('id'));
                    try {
                        $pavRepository->save($childPav);;
                    } catch (\Throwable $e) {
                        $GLOBALS['log']->error('Create child PAV failed: ' . $e->getMessage());
                    }
                    continue;
                }

                $pavService->prepareEntityForOutput($childPav);
                if ($childPav->get('isPavValueInherited') === false) {
                    $value = $childPav->get('value');
                    $parentValue = $parentPav->get('value');
                    if ($childPav->get('attributeType') === 'asset') {
                        $value = $childPav->get('valueId');
                        $parentValue = $parentPav->get('valueId');
                    }

                    if ($value === null || $parentValue === null) {
                        try {
                            $pavService->inheritPav($childPav->get('id'));
                        } catch (\Throwable $e) {
                            $GLOBALS['log']->error('Inherit PAV failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
