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

namespace Pim\Listeners;


use Atro\Core\EventManager\Event;
use Atro\Core\Utils\Util;
use Atro\Listeners\AbstractLayoutListener;

class ProductLayout extends AbstractLayoutListener
{
    public function relationships(Event $event)
    {
        $result = $event->getArgument('result');
        $isAdmin = $this->isAdminPage($event);
        $newResult = [];

        if ($this->isCustomLayout($event)) {
            if ($isAdmin) {
                return;
            }

            foreach ($result as $row) {
                if ($this->getConfig()->get('allowSingleClassificationForProduct', false)
                    && $row['name'] === 'classifications') {
                    continue;
                }

                if (str_starts_with($row['name'], "tab_")) {
                    if (!empty(substr($row['name'], 4)) && !empty($entity = $this->getEntityManager()->getEntity('AttributeTab', substr($row['name'], 4)))) {
                        if (!$this->getContainer()->get('acl')->checkEntity($entity, 'read')) {
                            continue 1;
                        }
                    }
                }
                $newResult[] = $row;
            }
        } else {
            foreach ($result as $row) {
                $newResult[] = $row;
            }
        }

        $event->setArgument('result', $newResult);
    }

    public function detail(Event $event)
    {
        $relatedEntity = $this->getRelatedEntity($event);
        $result = $event->getArgument('result');

        if ($relatedEntity === 'Category' && !str_contains(json_encode($result), 'ProductCategory__mainCategory')) {
            $result[0]['rows'][] = [['name' => 'ProductCategory__mainCategory'], false];
        }
        if ($relatedEntity === 'Product' && in_array($event->getArgument('relationLink'), ['parents', 'children']) && !str_contains(json_encode($result), 'ProductHierarchy__mainChild')) {
            $result[0]['rows'][] = [['name' => 'ProductHierarchy__mainChild'], false];
        }

        $event->setArgument('result', $result);
    }
}
