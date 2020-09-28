<?php

declare(strict_types=1);

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Json;
use Treo\Listeners\AbstractListener;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class ProductAttributeValueController
 *
 * @author r.ratsun@gmail.com
 */
class ProductAttributeValueController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionRead(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if (isset($data['result']->attributeId)) {
            $attribute = $this->getEntityManager()->getEntity('Attribute', $data['result']->attributeId);

            if (!empty($attribute)) {
                $data['result']->typeValue = $attribute->get('typeValue');

                // for multiLang fields
                if ($this->getConfig()->get('isMultilangActive')) {
                    foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                        $multiLangField = Util::toCamelCase('typeValue_' . strtolower($locale));
                        $data['result']->$multiLangField = $attribute->get($multiLangField);
                    }
                }
            }

            // set data
            $event->setArgument('result', $data['result']);
        }
    }

    /**
     * @param Event $event
     */
    public function beforeActionCreate(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if (is_array($data['data']->value)) {
            $data['data']->value = Json::encode($data['data']->value);
        }

        // for multiLang fields
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                $multiLangField = Util::toCamelCase('value_' . strtolower($locale));
                if (isset($data['data']->$multiLangField) && is_array($data['data']->$multiLangField)) {
                    $data['data']->$multiLangField = Json::encode($data['data']->$multiLangField);
                }
            }
        }

        // set data
        if (isset($data['result'])) {
            $event->setArgument('result', $data['result']);
        }
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     * @throws Error
     */
    public function beforeActionUpdate(Event $event)
    {
        // get data
        $data = $event->getArguments();

        if (isset($data['params']['id']) && isset($data['data']->productId)) {
            $productAttribute = $this->getEntityManager()->getEntity('ProductAttributeValue', $data['params']['id']);

            // check is ProductFamily attribute
            if (!empty($productAttribute->get('productFamilyAttributeId'))) {
                $message = $this
                    ->getLanguage()
                    ->translate(
                        'You can\'t change product in attribute from Product Family',
                        'exceptions',
                        'ProductAttributeValue'
                    );

                throw new BadRequest($message);
            }
        }
    }
}
