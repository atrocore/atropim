/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare','views/record/compare',
    Dep => Dep.extend({
        nonComparableAttributeFields: ['createdAt','modifiedAt','createdById','createdByName','modifiedById','modifiedByName','sortOrder'],
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
                    if(allAttributeIds.length > 0){
                        this.fieldsArr.push({
                           separator:true
                        });
                    }
                    allAttributeIds.forEach((attributeId) =>{
                        const pavCurrent = modelCurrent.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {};
                        const pavOther = modelOther.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {};
                        const pavModelCurrent = pavModel.clone();
                        const pavModelOther = pavModel.clone();
                        const attributeName = pavCurrent.attributeName ?? pavOther.attributeName;
                        const attributeChannel = pavCurrent.channelName ?? pavOther.channelName;
                        const productAttributeId = pavCurrent.id ?? pavOther.id;
                        const language = pavCurrent.language ?? pavOther.language;
                        pavModelCurrent.set(pavCurrent);
                        pavModelOther.set(pavOther);

                        this.createView( attributeId + 'Current', 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ` [data-id="${attributeId}"]  .current`,
                            name: "value",
                            nameName:"valueName",
                            model: pavModelCurrent,
                            readOnly: true,
                            mode: 'list',
                            inlineEditDisabled: true,
                        });


                        this.createView(attributeId + 'Other', 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ` [data-id="${attributeId}"]  .other`,
                            name: "value",
                            model: pavModelOther,
                            readOnly: true,
                            mode: 'list',
                            inlineEditDisabled: true,
                        });

                        this.fieldsArr.push({
                            isField: false,
                            attributeName: attributeName,
                            attributeChannel: attributeChannel,
                            language: language,
                            attributeId: attributeId,
                            productAttributeId: productAttributeId,
                            canQuickCompare: false,
                            current: attributeId + 'Current',
                            other: attributeId + 'Other',
                            different:  !this.areAttributeEquals(pavCurrent, pavOther)
                        });

                    })
                    this.wait(false);

                }, this)
            }, this)
        },

        areAttributeEquals(pavCurrent, pavOther){
            for (const nonComparableAttributeField of this.nonComparableAttributeFields) {
                delete pavCurrent[nonComparableAttributeField];
                delete  pavOther[nonComparableAttributeField];
            }
            return JSON.stringify(pavCurrent) === JSON.stringify(pavOther);
        },

    })
)