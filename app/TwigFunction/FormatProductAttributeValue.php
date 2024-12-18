<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Pim\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Espo\Core\ServiceFactory;
use Espo\ORM\EntityManager;
use Pim\Entities\ProductAttributeValue;

class FormatProductAttributeValue extends AbstractTwigFunction
{
    public function __construct(
        private readonly EntityManager  $entityManager,
        private readonly ServiceFactory $serviceFactory
    )
    {
    }

    public function run(ProductAttributeValue $entity): string|null
    {
        $attributeType = $entity->get('attributeType');
        $value = null;

        if (in_array($attributeType, ['alias', 'script'])) {
            $this->serviceFactory->create('ProductAttributeValue')->prepareEntityForOutput($entity);
        }

        switch ($attributeType) {
            case 'rangeInt':
                $value = ($entity->get('intValue') ?? 'Null') . ' – ' . ($entity->get('intValue1') ?? 'Null');

                break;
            case 'rangeFloat':
                $value = ($entity->get('floatValue') ?? 'Null') . ' – ' . ($entity->get('floatValue1') ?? 'Null');

                break;
            case 'extensibleEnum':
            case 'extensibleMultiEnum':
            case 'link':
            case 'linkMultiple':
                list($pavValue, $pavValueName) = match ($attributeType) {
                    'extensibleEnum'      => [$entity->get('value'), $entity->get('valueName')],
                    'extensibleMultiEnum' => [$entity->get('value'), $entity->get('valueNames')],
                    'link'                => [$entity->get('valueId'), $entity->get('valueName')],
                    'linkMultiple'        => [$entity->get('valueIds'), $entity->get('valueNames')]
                };

                if (empty($pavValue)) {
                    break;
                }

                if (is_array($pavValue)) {
                    $value = array_map(fn($v) => $pavValueName->{$v} ?? $pavValueName[$v] ?? $v, $pavValue);
                } else {
                    $value = $pavValueName ?: $pavValue;
                }

                break;
            case 'bool':
                if ($entity->get('boolValue') === null) {
                    break;
                }

                $value = $entity->get('boolValue') ? 'True' : 'False';

                break;
            case 'markdown':
                $mdParser = new \Parsedown();
                if (!$entity->get('textValue')) {
                    break;
                }

                $value = $mdParser->parse($entity->get('textValue'));

                break;
            case 'file':
                $valuePathData = $entity->get('valuePathsData');
                $value = $valuePathData['thumbnails']['medium'] ?? $entity->get('valueName') ?? $entity->get('valueId');

                break;
            default:
                $value = $entity->value ?? null;

                break;
        }

        if (in_array($entity->get('attributeType'), ['int', 'float', 'rangeInt', 'rangeFloat']) && $entity->get('referenceValue')) {
            if ($unit = $this->entityManager->getEntity('Unit', $entity->get('referenceValue'))) {
                $value .= ' ' . $unit->get('symbol') ?? $unit->get('name');
            }
        }

        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return $value;
    }
}