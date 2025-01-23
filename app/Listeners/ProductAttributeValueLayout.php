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

class ProductAttributeValueLayout extends AbstractLayoutListener
{
    public function list(Event $event)
    {
        if($this->isRelatedLayout($event)){
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
    }

    /**
     * @param Event $event
     */
    public function detail(Event $event)
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
                        $valueName = \Espo\Core\Utils\Util::toCamelCase('value_' . strtolower($locale));
                        if (is_array($field) && $field['name'] === $valueName) {
                            $result[$k]['rows'][$k1][$k2] = false;
                        }
                    }
                }
            }
        }

        $event->setArgument('result',  $result);
    }

}
