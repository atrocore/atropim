<?php
/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

declare(strict_types=1);

namespace Pim\Services;

use Espo\Core\Utils\Language;
use Espo\Core\Utils\Util;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use PdfGenerator\Services\DefaultPdfGenerator;

/**
 * ProductPdfTemplate class
 */
class ProductPdfTemplate extends DefaultPdfGenerator
{
    /**
     * @var Entity
     */
    protected $product;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var Language
     */
    protected $language;

    /**
     * @inheritDoc
     */
    protected function getEntryPointUrl(string $entityType, string $entitiesIds, string $template, array $additionalData = []): string
    {
        $url = parent::getEntryPointUrl($entityType, $entitiesIds, $template, $additionalData);

        if (!empty($additionalData['locale'])) {
            $url .= '&locale=' . $additionalData['locale'];
        }

        if (!empty($additionalData['channelId'])) {
            $url .= '&channel=' . $additionalData['channelId'];
        }

        return $url;
    }

    /**
     * @inheritDoc
     */
    protected function setFooter(): void
    {
        $this->pdfOptions['footerTemplate'] = '
            <div style="width: 100%; height: .7cm; transform: translateY(0.4cm); -webkit-print-color-adjust:exact; padding: 0 0.5cm; font-size: 8px; background-color: #efefef; display: flex; justify-content: space-between; align-items: center">
                <div>@  ' . date('Y') . ' AtroCore. This is just an example of an automatically generated PDF document.</div>
                <div class="pageNumber"></div>
            </div>';
    }

    /**
     * Data preparation for template
     *
     * @return array
     */
    public function getData(Entity $entity, array $data = []): array
    {
        $this->locale = !empty($data['locale']) ? $data['locale'] : null;
        $this->channel = !empty($data['channelId']) ? $data['channelId'] : null;
        $this->product = $entity;
        $this->language = $this->setLanguage();

        $fields = $this->getFields();

        $result = [
            'logo'                  => $this->getLogo(),
            'productName'           => $this->product->get('name'),
            'images'                => $this->getImages(),
            'attributes'            => $this->getAttributes()
        ];
        $result = array_merge($fields, $result);

        return ['result' => [$result]];
    }

    /**
     * @return string|null
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function getLogo(): ?string
    {
        if (empty($logoId = $this->getConfig()->get('companyLogoId'))) {
            return 'client/modules/treo-core/img/core_logo_dark.svg';
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $logoId);

        if (empty($attachment)) {
            return null;
        }

        return $this->prepareImgFile($attachment);
    }

    /**
     * Get overview fields
     *
     * @return array
     */
    protected function getFields(): array
    {
        $layout = $this->getContainer()->get('layout');
        $metadata = $this->getContainer()->get('metadata');
        $layoutDetail = json_decode($layout->get('Product', 'detail'), true);
        $rows = $layoutDetail[0]['rows'];
        $resultFields = [];
        foreach ($rows as $row) {
            foreach ($row as $field) {
                //skip if attr name of field is empty or fields are not needed for view
                if (empty($field['name']) || $field['name'] == 'ownerUser' || $field['name'] === 'image') {
                    continue;
                }

                // skip if field locale are not compatible
                $fieldDefs = $metadata->get(['entityDefs', 'Product', 'fields', $field['name']]);
                if (isset($fieldDefs['isMultilang']) && $this->locale != $fieldDefs['multilangLocale']) {
                    continue;
                }

                //get value
                $value = $this->getValue($field['name']);
                if (method_exists($this, 'prepare' . ucfirst($field['name']))) {
                    $resultFields = array_merge($resultFields, $this->{'prepare' . ucfirst($field['name'])}($value));
                } else {
                    $resultFields['overview'][] = [
                        'name' => $this->getLanguage()->translate($field['name'], 'fields', 'Product'),
                        'value' => $value
                    ];
                }
            }
        }
        return $resultFields;
    }

    /**
     * Translate field
     *
     * @param string $field
     *
     * @return mixed
     */
    protected function translateField(string $field)
    {
        $value = isset($this->product->getFields()[$field . $this->locale])
            ? $this->product->get($field . $this->locale)
            : $this->product->get($field);

        if (is_object($value) && $value instanceof Entity) {
            $translate = !empty($value->get('name' . $this->locale))
                ? $value->get('name' . $this->locale)
                : $value->get('name');
        } elseif (is_object($value) && $value instanceof EntityCollection) {
            $translate = array_column($value->toArray(), 'name' . $this->locale);
        } else {
            $translate = $value;
        }
        return $translate;
    }

    /**
     * Get attributes
     *
     * @return array
     */
    protected function getAttributes(): array
    {
        $result = [];
        $pavs = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where([
                'productId' => $this->product->id
            ])
            ->find();
        $attrLanguage = !empty($this->locale) ? $this->locale : 'main';

        if (count($pavs) > 0) {
            /** @var Entity $pav */
            foreach ($pavs as $pav) {
                /** @var Entity $attribute */
                if (empty($attribute = $pav->get('attribute')) || empty($group = $attribute->get('attributeGroup'))) {
                    continue;
                }

                if ($attribute->get('isMultilang') && $pav->get('language') !== $attrLanguage) {
                    continue;
                }

                $groupName = $group->get('name' . $this->camelCaseLocale());
                if (!isset($result[$groupName])) {
                    $result[$groupName] = [];
                }

                if (($pav->get('scope') == 'Global' && !empty($this->channel)) || ($pav->get('scope') == 'Channel' && $this->channel != $pav->get('channelId'))) {
                    continue;
                }

                $value = $pav->get('value');
                $type = $attribute->get('type');
                if ($type == 'bool') {
                    $value = (bool)$value;
                } elseif ($type == 'unit') {
                    $value .= ' ' . $pav->get('valueUnit');
                } elseif ($type == 'currency') {
                    $value .= ' ' . $pav->get('valueCurrency');
                } elseif ($type == 'asset' && !empty($value)) {
                    $attachment = $this->getEntityManager()->getEntity('Attachment', $value);

                    if (!empty($attribute)) {
                        $value = "<img src='{$this->prepareImgFile($attachment)}'>";
                    }
                }

                $result[$groupName][] = [
                    'name' => $attribute->get('name' . $this->camelCaseLocale()),
                    'value' => $value
                ];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getImages(): array
    {
        $result = ['main' => null, 'list' => null];

        if ($this->getContainer()->get('metadata')->isModuleInstalled('Dam')) {
            $sql = "SELECT a.file_id AS attachmentId, pa.channel, pa.is_main_image AS isMainImage FROM asset a
                    JOIN product_asset pa ON pa.asset_id = a.id AND pa.deleted = 0
                    JOIN attachment at on at.id = a.file_id AND at.deleted = 0
                    WHERE pa.product_id = '{$this->product->id}' AND at.type IN ('image/jpeg', 'image/png', 'image/gif') AND a.deleted = 0
                    ORDER BY pa.sorting ASC";

            $assets = $this
                ->getEntityManager()
                ->nativeQuery($sql)
                ->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($assets)) {
                if (!empty($mainImage = $this->getMainImage($assets))) {
                    $result['main'] = $mainImage;
                }

                foreach ($assets as $asset) {
                    if (count($result) < 3) {
                        if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $asset['attachmentId']))) {
                            $result['list'][] = $this->prepareImgFile($attachment);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array $assets
     *
     * @return string|null
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function getMainImage(array &$assets): ?string
    {
        $result = null;

        foreach ($assets as $index => $asset) {
            if ($asset['isMainImage']) {
                if ((!empty($this->channel) && $this->channel == $asset['channel']) || (empty($this->channel) && empty($asset['channel']))) {
                    array_splice($assets, $index, 1);

                    if (!empty($attachment = $this->getEntityManager()->getEntity('Attachment', $asset['attachmentId']))) {
                        $result = $this->prepareImgFile($attachment);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param Attachment $attach
     *
     * @return string
     */
    protected function prepareImgFile(Attachment $attach): ?string
    {
        $path = 'upload/files/'
            . $attach->get('storageFilePath')
            . '/'
            . $attach->get('name');

        if (file_exists($path)) {
            // prepare data
            $content = base64_encode(file_get_contents($path));
            // get mimeType in DB
            $mimeType = $attach->get('type');
            // if mimeType null in DB
            if (empty($mimeType)) {
                // create obj finfo, for get mimeType
                $fileInfo = new finfo(FILEINFO_MIME_TYPE);
                // get mimeType
                $mimeType = $fileInfo->buffer($content);
            }
            $imageUrl = "data:$mimeType;base64, $content";
        } else {
            $imageUrl = null;
        }

        return $imageUrl;
    }

    /**
     * @param $field
     * @return int|mixed|string
     */
    protected function getValue($field)
    {
        //get value
        $value = $this->translateField($field);
        //check value
        if (is_array($value)) {
            $value = join(', ', $value);
        } elseif (is_bool($value)) {
            $value = (int)$value;
        }

        return $value;
    }

    /**
     * @return string
     */
    protected function camelCaseLocale(): string {
        return $this->locale ? Util::toCamelCase(strtolower($this->locale), '_', true) : '';
    }

    /**
     * @return Language
     */
    protected function setLanguage(): Language
    {
        if (empty($this->locale)) {
            return parent::getLanguage();
        } else {
            return new Language($this->getContainer(), $this->locale);
        }
    }

    /**
     * @return Language
     */
    protected function getLanguage(): Language
    {
        return $this->language;
    }
}
