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

namespace Pim;

use Espo\Core\Utils\Json;
use Treo\Core\ModuleManager\AbstractModule;

class Module extends AbstractModule
{
    /**
     * @inheritdoc
     */
    public static function getLoadOrder(): int
    {
        return 5120;
    }

    /**
     * @inheritDoc
     */
    public function loadMetadata(\stdClass &$data)
    {
        parent::loadMetadata($data);

        $data = Json::decode(Json::encode($data), true);

        if ($this->container->get('metadata')->isModuleInstalled('Dam')) {
            $data['dashlets'] = array_merge_recursive($data['dashlets'], $data['dashletsForDam']);
            $data['clientDefs'] = array_merge_recursive($data['clientDefs'], $data['clientDefsForDam']);
            $data['entityDefs'] = array_merge_recursive($data['entityDefs'], $data['entityDefsForDam']);
            $data['scopes'] = array_merge_recursive($data['scopes'], $data['scopesForDam']);
        }

        $data = Json::decode(Json::encode($data));
    }
}
