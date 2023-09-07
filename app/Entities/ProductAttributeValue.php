<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Pim\Entities;

use Espo\Core\Templates\Entities\Relationship;
use Espo\Core\Utils\Json;

class ProductAttributeValue extends Relationship
{
    protected $entityType = "ProductAttributeValue";

    public function isAttributeChanged($name)
    {
        if ($name === 'value') {
            return parent::isAttributeChanged('boolValue')
                || parent::isAttributeChanged('dateValue')
                || parent::isAttributeChanged('datetimeValue')
                || parent::isAttributeChanged('intValue')
                || parent::isAttributeChanged('floatValue')
                || parent::isAttributeChanged('varcharValue')
                || parent::isAttributeChanged('textValue');
        }

        return parent::isAttributeChanged($name);
    }

    public function setData(array $data): void
    {
        $this->set('data', $data);
    }

    public function setDataParameter(string $key, $value): void
    {
        $data = $this->getData();
        $data[$key] = $value;

        $this->set('data', $data);
    }

    public function getDataParameter(string $key)
    {
        $data = $this->getData();

        return isset($data[$key]) ? $data[$key] : null;
    }

    public function getData(): array
    {
        $data = $this->get('data');

        return empty($data) ? [] : Json::decode(Json::encode($data), true);
    }

    public function getChannelLanguages(): array
    {
        return $this->getRepository()->getChannelLanguages((string)$this->get('channelId'));
    }

    public function __get($name)
    {
        $this->getRepository()->getValueConverter()->convertFrom($this, $this->get('attribute'), false);

        return parent::__get($name);
    }

    protected function getRepository(): \Pim\Repositories\ProductAttributeValue
    {
        return $this->entityManager->getRepository('ProductAttributeValue');
    }
}
