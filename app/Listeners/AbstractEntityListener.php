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

use Espo\Core\ServiceFactory;
use Atro\Listeners\AbstractListener;

abstract class AbstractEntityListener extends AbstractListener
{
    protected function getServiceFactory(): ServiceFactory
    {
        return $this->getContainer()->get('serviceFactory');
    }

    protected function translate(string $key, string $label, $scope = ''): string
    {
        /** @var \Atro\Core\Utils\Language $language */
        $language = $this->getContainer()->get('language');
        return $language->translate($key, $label, $scope);
    }
}
