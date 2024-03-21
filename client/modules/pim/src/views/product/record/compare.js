/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('pim:views/product/record/compare','views/record/compare',
    Dep => Dep.extend({
        setup(){
            this.getModelFactory().create('ProductAttributeValue', function(pavModel){
                this.getModelFactory().create(this.scope, function (model) {
                    var modelCurrent = this.model;
                    var modelOther = model.clone();
                    modelOther.set(this.distantModelAttribute);

                    this.fieldsArr = [];

                    let fieldDefs =  this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

                    Object.entries(fieldDefs).forEach(function ([field, fieldDef]) {
                        if(field === "data"){
                            return;
                        }

                        if(this.nonComparableFields.includes(field)){
                            return ;
                        }

                        let type = fieldDef['type'];

                        let fieldId = field;
                        if (type === 'asset' || type === 'link') {
                            fieldId = field + 'Id';
                        } else if (type === 'linkMultiple') {
                            fieldId = field + 'Ids';
                        }



                        if(!this.distantModelAttribute.hasOwnProperty(fieldId)){
                            return ;
                        }

                        if (model.getFieldParam(field, 'isMultilang') && !modelCurrent.has(fieldId) && !modelOther.has(fieldId)) {
                            return;
                        }


                        let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);
                        this.createView(field + 'Current', viewName, {
                            el: this.options.el + ' .current',
                            model: modelCurrent,
                            readOnly: true,
                            defs: {
                                name: field,
                                label: field + ' 11'
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });

                        this.createView(field + 'Other', viewName, {
                            el: this.options.el + ' .other',
                            model: modelOther,
                            readOnly: true,
                            defs: {
                                name: field
                            },
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });

                        let htmlTag = 'code';

                        if (type === 'color' || type === 'enum') {
                            htmlTag = 'span';
                        }

                        const isLink = type === 'link' || type === 'linkMultiple';
                        isLinkMultiple = type === 'linkMultiple';

                        const values = (isLinkMultiple && modelCurrent.get(fieldId)) ? modelCurrent.get(fieldId).map(v => {
                            return {
                                id:v,
                                name: modelCurrent.get(field+'Names') ? (modelCurrent.get(field+'Names')[v] ?? v) : v
                            }
                        }) : null;

                        this.fieldsArr.push({
                            isField: true,
                            field: field,
                            label:fieldDef['label'] ?? field,
                            current: field + 'Current',
                            htmlTag: htmlTag,
                            other: field + 'Other',
                            isLink: isLink && this.hideQuickMenu !== true,
                            foreignScope: isLink ? this.links[field].entity : null,
                            foreignId: isLink ? modelCurrent.get(fieldId)?.toString() : null,
                            isLinkMultiple: isLinkMultiple,
                            values: values,
                            different:  !this.areEquals(modelCurrent, modelOther, field, fieldDef)
                        });

                    }, this);

                    const currentAttributeIds =  modelCurrent.get('productAttributeValues').map(pav =>pav.attributeId)
                    const otherPavAttributeIds =  modelOther.get('productAttributeValues').map(pav =>pav.attributeId)

                    const allAttributeIds = Array.from(new Set([...currentAttributeIds, ...otherPavAttributeIds]));

                    allAttributeIds.forEach((attributeId) =>{
                        const pavCurrent = modelCurrent.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {};
                        const pavOther = modelOther.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {};
                        const pavModelCurrent = pavModel.clone();
                        const pavModelOther = pavModel.clone();
                        const attributeName = pavCurrent.attributeName ?? pavOther.attributeName;
                        pavModelCurrent.set(pavCurrent);
                        pavModelCurrent.set(pavOther);

                        this.createView( attributeId + 'Current', 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ' .currentAttribute',
                            name: attributeName,
                            model: pavModelCurrent,
                            readOnly: true,
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });

                        this.createView(attributeId + 'Other', 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ' .otherAttribute',
                            name: attributeName,
                            model: pavModelOther,
                            readOnly: true,
                            mode: 'detail',
                            inlineEditDisabled: true,
                        });

                        this.fieldsArr.push({
                            isField: false,
                            attributeName: attributeName,
                            attributeId: attributeId,
                            current: attributeId + 'Current',
                            other: attributeId + 'Other',
                            different:  this.areAttributeEquals(pavCurrent, pavOther)
                        });

                    })
                    this.wait(false);

                }, this)
            }, this)
        },

        areAttributeEquals(pavCurrent, pavOther){
            return pavCurrent.toString() === pavOther.toString();
        }

    })
)