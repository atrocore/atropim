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
use Espo\Core\Utils\Util;
use Atro\Listeners\AbstractListener;
use Pim\SelectManagers\ProductAttributeValue;

/**
 * Class Language
 */
class Language extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function modify(Event $event)
    {
        $data = $event->getArgument('data');

        foreach ($data as $l => $rows) {
            foreach ($this->getMetadata()->get(['entityDefs', 'Product', 'fields'], []) as $fields => $fieldDefs) {
                if (!empty($fieldDefs['attributeId'])) {
                    $attributeName = empty($fieldDefs['attributeName']) ? $fieldDefs['attributeId'] : $fieldDefs['attributeName'];
                    if (isset($fieldDefs[Util::toCamelCase('attribute_name_' . strtolower($l))])) {
                        $attributeName = $fieldDefs[Util::toCamelCase('attribute_name_' . strtolower($l))];
                    }
                    if (!empty($fieldDefs['multilangLocale'])) {
                        $attributeName .= ' / ' . $fieldDefs['multilangLocale'];
                    }
                    $data[$l]['Product']['fields'][$fields] = $attributeName;
                }
            }
        }

        $this->addTabTranslations($data);

        $event->setArgument('data', $data);
    }

    protected  function addTabTranslations(&$data){
        $tabs = $this->getEntityManager()->getRepository('AttributeTab')->getSimplifyTabs();
        $locales[] = 'main';
        $locales = array_merge($locales, $this->getConfig()->get('inputLanguageList', []));

        foreach ($tabs as $tab){
            foreach ($locales as $locale) {
                if($locale === 'main'){
                    $mainLocal = $this->getConfig()->get('mainLanguage');
                    $data[$mainLocal]['Global']['labels']['tab_'.$tab['id']] = $tab['name'];
                    continue;
                }

                if(empty($this->getConfig()->get('isMultilangActive'))){
                    $data[$locale]['Global']['labels']['tab_'.$tab['id']] = $tab['name'];
                    continue;
                }

                $nameColumn = 'name_'.strtolower($locale);
                $data[$locale]['Global']['labels']['tab_'.$tab['id']] = $tab[$nameColumn];
            }
        }
    }
}
