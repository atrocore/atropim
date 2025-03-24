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

namespace Pim\Services;

use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Services\Base;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Pim\Core\ValueConverter;

class AttributeValue extends Base
{
    protected $mandatorySelectAttributeList
        = [
            'language',
            'attributeId',
            'attributeName',
            'intValue',
            'intValue1',
            'boolValue',
            'dateValue',
            'datetimeValue',
            'floatValue',
            'floatValue1',
            'varcharValue',
            'textValue',
            'referenceValue'
        ];

    public function prepareEntityForOutput(Entity $entity)
    {
        $this->prepareEntity($entity);

        parent::prepareEntityForOutput($entity);
    }

    public function prepareEntity(Entity $entity, bool $clear = true): void
    {
        $attribute = $this->getEntityManager()->getRepository('Attribute')->get($entity->get('attributeId'));
        if (empty($attribute)) {
            throw new NotFound();
        }

        $userLanguage = $this->getUser()->getLanguage();
        if (!empty($userLanguage)) {
            $nameField = Util::toCamelCase('name_' . strtolower($userLanguage));
            if ($attribute->has($nameField) && !empty($attribute->get($nameField))) {
                $entity->set('attributeName', $attribute->get($nameField));
            }
            $tooltipFieldName = Util::toCamelCase('tooltip_' . strtolower($userLanguage));
            if ($attribute->has($tooltipFieldName) && !empty($attribute->get($tooltipFieldName))) {
                $tooltip = $attribute->get($tooltipFieldName);
            }
        }

        if (empty($tooltip)) {
            $tooltip = $attribute->get('tooltip');
        }

        $entity->set('attributeTooltip', $tooltip);
        $entity->set('attributeEntityType', $attribute->get('entityType'));
//        $entity->set('attributeEntityField', $attribute->get('entityField'));
//        $entity->set('attributeFileTypeId', $attribute->get('fileTypeId'));
//        $entity->set('attributeIsMultilang', $attribute->get('isMultilang'));
//        $entity->set('attributeCode', $attribute->get('code'));
//        $entity->set('prohibitedEmptyValue', $attribute->get('prohibitedEmptyValue'));
//        $entity->set('attributeGroupId', $attribute->get('attributeGroupId'));
//        $entity->set('attributeGroupName', $attribute->get('attributeGroupName'));
//        $entity->set('attributeNotNull', $attribute->get('notNull'));
//        $entity->set('attributeTrim', $attribute->get('trim'));


//        if (!empty($attribute->get('useDisabledTextareaInViewMode')) && in_array($entity->get('attributeType'), ['text', 'varchar', 'wysiwyg'])) {
//            $entity->set('useDisabledTextareaInViewMode', $attribute->get('useDisabledTextareaInViewMode'));
//        }

//        $entity->set('sortOrder', $attribute->get('sortOrder'));

//        $entity->set('channelCode', null);
//        if (!empty($entity->get('channelId')) && !empty($channel = $entity->get('channel'))) {
//            $entity->set('channelCode', $channel->get('code'));
//        }

        $this->getInjection('container')->get(ValueConverter::class)->convertFrom($entity, $attribute, $clear);

//        if ($attribute->get('measureId')) {
//            $entity->set('attributeMeasureId', $attribute->get('measureId'));
//            $this->prepareUnitFieldValue($entity, 'value', [
//                'measureId' => $attribute->get('measureId'),
//                'type'      => $attribute->get('type'),
//                'mainField' => 'value'
//            ]);
//        }
//
//        $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
//        if (in_array($entity->get('attributeType'), array_keys($dropdownTypes))) {
//            $entity->set('attributeIsDropdown', $attribute->get('dropdown'));
//        }
//
//        if ($entity->get('channelId') === '') {
//            $entity->set('channelId', null);
//        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
        $this->addDependency('container');
        $this->addDependency(ValueConverter::class);
    }
}
