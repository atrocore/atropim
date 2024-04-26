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

        $languages = [];
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            $languages = $this->getConfig()->get('inputLanguageList', []);
        }

        foreach ($data as $l => $rows) {
            if (isset($rows['Locale']['fields']['language'])) {
                $languageLabel = $rows['Locale']['fields']['language'];
            } elseif (isset($data['en_US']['Locale']['fields']['language'])) {
                $languageLabel = $data['en_US']['Locale']['fields']['language'];
            } else {
                $languageLabel = 'Language';
            }

            if (isset($rows['Global']['labels']['mainLanguage'])) {
                $mainLanguageLabel = $rows['Global']['labels']['mainLanguage'];
            } elseif (isset($data['en_US']['Global']['labels']['mainLanguage'])) {
                $mainLanguageLabel = $data['en_US']['Global']['labels']['mainLanguage'];
            } else {
                $mainLanguageLabel = 'Main Language';
            }

            if (isset($rows['Global']['scopeNames']['Channel'])) {
                $channelLabel = $rows['Global']['scopeNames']['Channel'];
            } elseif (isset($data['en_US']['Global']['scopeNames']['Channel'])) {
                $channelLabel = $data['en_US']['Global']['scopeNames']['Channel'];
            } else {
                $channelLabel = 'Channel';
            }

            $data[$l]['ProductAttributeValue']['boolFilters'][ProductAttributeValue::createLanguagePrismBoolFilterName('main')] = $languageLabel . ': ' . $mainLanguageLabel;
            foreach ($languages as $language) {
                $data[$l]['ProductAttributeValue']['boolFilters'][ProductAttributeValue::createLanguagePrismBoolFilterName($language)] = $languageLabel . ': ' . $language;
                $camelCaseLocale = ucfirst(Util::toCamelCase(strtolower($language)));
                if (!empty($data[$l]['Attribute']['fields']["name"])) {
                    $data[$l]['ClassificationAttribute']['fields']["attributeName$camelCaseLocale"] = $data[$l]['Attribute']['fields']["name"] . ' / ' . $language;
                }
            }

            foreach (['ProductAttributeValue', 'ProductFile'] as $entityType) {
                $callback = '\\Pim\\SelectManagers\\' . $entityType . '::createScopePrismBoolFilterName';
                $data[$l][$entityType]['boolFilters'][call_user_func($callback, 'global')] = $channelLabel . ': Global';
                foreach ($this->getMetadata()->get(['clientDefs', $entityType, 'channels'], []) as $channel) {
                    $data[$l][$entityType]['boolFilters'][call_user_func($callback, $channel['id'])] = $channelLabel . ': ' . $channel['name'];
                }
            }

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

                $nameColumn = 'name_'.strtolower($locale);
                $data[$locale]['Global']['labels']['tab_'.$tab['id']] = $tab[$nameColumn];
            }
        }
    }
}
