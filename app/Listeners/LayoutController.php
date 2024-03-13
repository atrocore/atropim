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
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Atro\Listeners\AbstractListener;

/**
 * Class LayoutController
 */
class LayoutController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionRead(Event $event)
    {
        /** @var string $scope */
        $scope = $event->getArgument('params')['scope'];

        /** @var string $name */
        $name = $event->getArgument('params')['name'];

        /** @var bool $isAdminPage */
        $isAdminPage = $event->getArgument('request')->get('isAdminPage') === 'true';

        $method = 'modify' . $scope . ucfirst($name);
        $methodAdmin = $method . 'Admin';

        if (!$isAdminPage && method_exists($this, $method)) {
            $this->{$method}($event);
        } else {
            if ($isAdminPage && method_exists($this, $methodAdmin)) {
                $this->{$methodAdmin}($event);
            }
        }
    }

    protected function modifyAssetListSmall(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        if (!file_exists('custom/Espo/Custom/Resources/layouts/Asset/listSmall.json')) {
            $result[] = ['name' => 'ProductAsset__channel'];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyAssetDetailSmall(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        if (!file_exists('custom/Espo/Custom/Resources/layouts/Asset/detailSmall.json')) {
            $result[0]['rows'][] = [['name' => 'ProductAsset__isMainImage'], ['name' => 'ProductAsset__sorting']];
            $result[0]['rows'][] = [['name' => 'ProductAsset__scope'], ['name' => 'ProductAsset__channel']];

            $result[0]['rows'][] = [['name' => 'CategoryAsset__isMainImage'], ['name' => 'CategoryAsset__sorting']];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyChannelListSmall(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        if (!file_exists('custom/Espo/Custom/Resources/layouts/Channel/listSmall.json')) {
            $result[] = ['name' => 'ProductChannel__isActive'];
        }

        $event->setArgument('result', Json::encode($result));
    }

    protected function modifyChannelDetailSmall(Event $event)
    {
        $result = Json::decode($event->getArgument('result'), true);

        if (!file_exists('custom/Espo/Custom/Resources/layouts/Channel/detailSmall.json')) {
            $result[0]['rows'][] = [['name' => 'ProductChannel__isActive'], false];
        }

        $event->setArgument('result', Json::encode($result));
    }

    /**
     * @param Event $event
     */
    protected function modifyAttributeDetail(Event $event)
    {
        /** @var array $result */
        $result = Json::decode($event->getArgument('result'), true);

        if ($this->getConfig()->get('isMultilangActive', false)) {
            $multilangField = ['name' => 'isMultilang', 'inlineEditDisabled' => false];

            $result[0]['rows'][] = [$multilangField, false];
        }

        $event->setArgument('result', Json::encode($result));
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
        $result = Json::decode($event->getArgument('result'), true);

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

        $event->setArgument('result', Json::encode($result));
    }

    /**
     * @param Event $event
     */
    protected function modifyAttributeDetailSmall(Event $event)
    {
        $this->modifyAttributeDetail($event);
    }

    /**
     * @param Event $event
     */
    protected function modifyProductRelationshipsLayout(Event $event, bool $isAdmin)
    {
        $result = Json::decode($event->getArgument('result'), true);
        $newResult = [];

        if ($this->getContainer()->get('layout')->isCustom('Product', 'relationships')) {
            if ($isAdmin) {
                return;
            }

            foreach ($result as $row) {
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

        $event->setArgument('result', Json::encode($newResult));
    }

    /**
     * @param Event $event
     */
    protected function modifyProductRelationships(Event $event)
    {
        $this->modifyProductRelationshipsLayout($event, false);
    }

    protected function modifyProductRelationshipsAdmin(Event $event)
    {
        $this->modifyProductRelationshipsLayout($event, true);
    }

    /**
     * @param Event $event
     */
    protected function modifyCategoryRelationshipsAdmin(Event $event)
    {
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
