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

namespace Pim\Listeners;

use Atro\Core\EventManager\Event;
use Atro\Listeners\AbstractLayoutListener;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Atro\Listeners\AbstractListener;

class Layout extends AbstractLayoutListener
{
    
    protected function modifyProductAttributeValueListSmall(Event $event)
    {
        $result = $event->getArgument('result');
        foreach ($result as &$item) {
            if (!empty($item['name'])) {
                if ($item['name'] === 'attribute') {
                    $item['view'] = 'pim:views/product-attribute-value/fields/attribute-with-required-sign';
                }
                if ($item['name'] === 'icons') {
                    $item['customLabel'] = '';
                }
            }
        }
        $event->setArgument('result',  $result);
    }

    protected function modifyClassificationAttributeListSmall(Event $event)
    {
        $result = $event->getArgument('result');
        foreach ($result as &$item) {
            if (!empty($item['name']) && $item['name'] === 'attribute') {
                $item['view'] = 'pim:views/classification-attribute/fields/attribute-with-inheritance-sign';
            }
        }
        $event->setArgument('result',  $result);
    }

    protected function modifyFileListSmall(Event $event)
    {
        if ($this->isCustomLayout($event)) {
            return;
        }

        $result = $event->getArgument('result');
        $result[] = ['name' => 'ProductFile__channel'];

        $event->setArgument('result',  $result);
    }

    protected function modifyFileDetailSmall(Event $event)
    {
        if ($this->isCustomLayout($event)) {
            return;
        }
        $result = $event->getArgument('result');

        $result[0]['rows'][] = [['name' => 'ProductFile__isMainImage'], ['name' => 'ProductFile__sorting']];
        $result[0]['rows'][] = [['name' => 'ProductFile__channel'], false];

        $result[0]['rows'][] = [['name' => 'CategoryFile__isMainImage'], ['name' => 'CategoryFile__sorting']];

        $event->setArgument('result',  $result);
    }

    protected function modifyChannelListSmall(Event $event)
    {
        if ($this->isCustomLayout($event)) {
            return;
        }

        $result = $event->getArgument('result');
        $result[] = ['name' => 'ProductChannel__isActive'];

        $event->setArgument('result',  $result);
    }

    protected function modifyChannelDetailSmall(Event $event)
    {
        if ($this->isCustomLayout($event)) {
            return;
        }

        $result = $event->getArgument('result');
        $result[0]['rows'][] = [['name' => 'ProductChannel__isActive'], false];

        $event->setArgument('result',  $result);
    }

    /**
     * @param Event $event
     */
    protected function modifyAttributeDetail(Event $event)
    {
        /** @var array $result */
        $result = $event->getArgument('result');

        foreach ($result as $panel) {
            foreach ($panel['rows'] as $row) {
                if (in_array('isMultilang', array_column($row, 'name'))) {
                    return;
                }
            }
        }

        if ($this->getConfig()->get('isMultilangActive', false)) {
            $multilangField = ['name' => 'isMultilang', 'inlineEditDisabled' => false];

            $result[0]['rows'][] = [$multilangField, false];
        }

        $event->setArgument('result',  $result);
    }

    /**
     * @param Event $event
     */
    protected function modifyProductAttributeValueDetailSmall(Event $event)
    {
        if (empty($this->getConfig()->get('isMultilangActive'))) {
            return;
        }

        if (empty($locales = $this->getConfig()->get('inputLanguageList', []))) {
            return;
        }

        /** @var array $result */
        $result = $event->getArgument('result');

        foreach ($result as $k => $panel) {
            foreach ($panel['rows'] as $k1 => $row) {
                foreach ($row as $k2 => $field) {
                    foreach ($locales as $locale) {
                        $valueName = Util::toCamelCase('value_' . strtolower($locale));
                        if (is_array($field) && $field['name'] === $valueName) {
                            $result[$k]['rows'][$k1][$k2] = false;
                        }
                    }
                }
            }
        }

        $event->setArgument('result',  $result);
    }

    /**
     * @param Event $event
     */
    protected function modifyProductRelationships(Event $event)
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
                if ($row['name'] == 'productAttributeValues') {
                    $panels = $this->getMetadata()->get(['clientDefs', 'Product', 'bottomPanels', 'detail'], []);
                    foreach ($panels as $panel) {
                        if (!empty($panel['tabId'])) {
                            if (!$isAdmin) {
                                $entity = $this->getEntityManager()->getEntity('AttributeTab', $panel['tabId']);
                                // check if user can read on AttributeTab
                                if (!$this->getContainer()->get('acl')->checkEntity($entity, 'read')) {
                                    continue 1;
                                }
                            }
                            $newResult[] = ['name' => $panel['name']];
                        }
                    }
                }
                $newResult[] = $row;
            }
        }

        $event->setArgument('result', $newResult);
    }


    /**
     * @return array
     */
    protected function getInputLanguageList(): array
    {
        $result = [];
        if ($this->getConfig()->get('isMultilangActive', false)) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $locale) {
                $result[$locale] = ucfirst(Util::toCamelCase(strtolower($locale)));
            }
        }

        return $result;
    }

}
