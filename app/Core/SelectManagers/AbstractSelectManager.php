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

namespace Pim\Core\SelectManagers;

use Espo\Core\Services\Base as BaseService;

/**
 * Class of AbstractSelectManager
 */
abstract class AbstractSelectManager extends \Espo\Core\SelectManagers\Base
{

    /**
     * @var array
     */
    protected $selectData = [];

    /**
     * Get select params
     *
     * @param array $params
     * @param bool  $withAcl
     * @param bool  $checkWherePermission
     *
     * @return array
     */
    public function getSelectParams(array $params, $withAcl = false, $checkWherePermission = false)
    {
        // set select data
        $this->selectData = $params;

        return parent::getSelectParams($params, $withAcl, $checkWherePermission);
    }

    /**
     * Get select data
     *
     * @param string $key
     *
     * @return array
     */
    protected function getSelectData($key = '')
    {
        $result = [];

        if (empty($key)) {
            $result = $this->selectData;
        } elseif (isset($this->selectData[$key])) {
            $result = $this->selectData[$key];
        }

        return $result;
    }

    /**
     * Is has bool filter with defined key name
     *
     * @param string $key
     *
     * @return bool
     */
    protected function hasBoolFilter(string $key): bool
    {
        // prepare result
        $result = false;

        foreach ($this->getSelectData('where') as $row) {
            if ($row['type'] == 'bool'
                && !empty($row['value'])
                && is_array($row['value'])
                && in_array($key, $row['value'])) {
                // prepare result
                $result = true;

                break;
            }
        }

        return $result;
    }

    /**
     * Get Condition for boolFilter
     *
     * @param string $filterName
     *
     * @return mixed
     */
    protected function getSelectCondition(string $filterName)
    {
        foreach ($this->getSelectData('where') as $key => $row) {
            if ($row['type'] == 'bool' && isset($row['data']) && array_key_exists($filterName, $row['data'])) {
                $condition = $row['data'][$filterName];
            }
        }

        return $condition ?? false;
    }

    /**
     * Create Service
     *
     * @param string $name
     *
     * @return BaseService
     */
    protected function createService(string $name): BaseService
    {
        return $this
            ->getEntityManager()
            ->getContainer()
            ->get('serviceFactory')
            ->create($name);
    }
}
