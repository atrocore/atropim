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

namespace Pim\Controllers;

use Atro\Core\Templates\Controllers\ReferenceData;
use Atro\Controllers\AbstractRecordController;

class AttributePanel extends ReferenceData
{
    public function actionCreateLink($params, $data, $request)
    {
        return AbstractRecordController::actionCreateLink($params, $data, $request);
    }

    public function actionRemoveLink($params, $data, $request)
    {
        return AbstractRecordController::actionRemoveLink($params, $data, $request);
    }

    public function actionUnlinkAll($params, $data, $request)
    {
        return AbstractRecordController::actionUnlinkAll($params, $data, $request);
    }
}
