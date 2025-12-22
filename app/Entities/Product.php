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

namespace Pim\Entities;

use Atro\Core\Exceptions\Error;
use Espo\Core\Utils\Json;
use Atro\Core\Utils\Util;
use Atro\Core\Templates\Entities\Hierarchy;
use Espo\ORM\EntityCollection;

class Product extends Hierarchy
{
    /**
     * Get product categories
     *
     * @return EntityCollection
     * @throws Error
     */
    public function getCategories(): EntityCollection
    {
        if (empty($this->get('id'))) {
            throw new Error('No such Product');
        }

        return $this->get('categories', ['disableCache' => true]);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $result = [];

        if (!empty($this->get('data'))) {
            $result = Json::decode(Json::encode($this->get('data')), true);
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setData(array $data): Product
    {
        $this->set('data', $data);

        return $this;
    }

    /**
     * @param string $field
     *
     * @return mixed|null
     */
    public function getDataField(string $field)
    {
        $data = $this->getData();

        return $data[$field] ?? null;
    }

    /**
     * @param string $field
     * @param        $value
     *
     * @return $this
     */
    public function setDataField(string $field, $value): Product
    {
        $data = $this->getData();

        $data[$field] = $value;

        return $this->setData($data);
    }

    /**
     * @param string $locale
     *
     * @return null|string
     */
    protected function getLocale(string $locale): ?string
    {
        // prepare locale
        $locale = Util::toUnderScore($locale);

        // get input languages list
        $inputLanguageList = $this
            ->getEntityManager()
            ->getRepository($this->getEntityType())
            ->getInputLanguageList();

        foreach ($inputLanguageList as $item) {
            if (strtolower($item) == $locale) {
                return $item;
            }
        }

        return null;
    }

}
