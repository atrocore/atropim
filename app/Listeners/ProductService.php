<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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

                    if ($value === null) {
                        try {
                            $pavService->inheritPav($childPav);
                        } catch (\Throwable $e) {
                            $GLOBALS['log']->error('Inherit PAV failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }
}
