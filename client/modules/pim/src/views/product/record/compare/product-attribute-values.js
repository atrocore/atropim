/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/product-attribute-values', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        template: 'pim:product/record/compare/product-attribute-values',

        attributesArr: [],

        attributeList: [],

        tabId: null,

        model: null,

        groupPavsData: [],

        defaultPavModel: null,

        noData: false,

        comparableAttributeFields: ['value', 'valueId', 'valueIds', 'attributeId', 'channelId', 'valueUnitId'],

        events: {
            'change input[type="radio"].field-radio': function (e) {
                e.stopPropagation();
                let modelId = e.currentTarget.value;
                let key = e.currentTarget.name;
                let attrData;
                for (const group of this.attributeList) {
                    attrData = group.attributes.find(attr => attr.key === key);
                    if (attrData) {
                        break;
                    }
                }
                this.updateFieldState(attrData, modelId);
            },
        },

        setup() {
            this.tabId = this.options.defs?.tabId;

            Dep.prototype.setup.call(this);

            this.deferRendering = true;
            this.attributeList = [];
            this.groupPavsData = [];
            this.attributesArr = [];
            this.noData = false;
            this.defaultPavModel = null;
            this.selectedModelId = this.models[0].id;
            this.listenTo(this.model, 'select-model', (modelId) => {
                this.selectedModelId = modelId;
                this.attributeList.forEach(group => {
                    group.attributes.forEach(attrData => {
                        this.updateFieldState(attrData, modelId);
                    });
                });
            });
        },

        fetchModelsAndSetup() {
            this.wait(true)
            this.getModelFactory().create('ProductAttributeValue', function (pavModel) {

                let modelPavs = {};
                let promises = [];

                this.collection.models.forEach(model => {
                    let param = {
                        tabId: this.tabId,
                        productId: model.get('id'),
                        fieldFilter: ['allValues'],
                        languageFilter: ['allLanguages'],
                        scopeFilter: ['allChannels']
                    };
                    promises.push(new Promise(resolve => {
                        this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', param).success(res => {
                            modelPavs[model.get('id')] = res;
                            resolve();
                        });
                    }));
                });

                Promise.all(promises).then(() => {
                    let currentGroupPavs = modelPavs[this.baseModel.id];
                    let otherGroupPavsPerModels = [];
                    let tmp = {}
                    delete modelPavs[this.model.id];
                    this.collection.models.forEach((model) => {
                        if (modelPavs[model.get('id')]) {
                            otherGroupPavsPerModels.push(modelPavs[model.get('id')]);
                        }
                    });

                    currentGroupPavs.forEach((group) => {
                        tmp[group.key] = {
                            id: group.id,
                            key: group.key,
                            label: group.label,
                            othersRelationItemsPerModels: [],
                            currentCollection: group.collection.map(p => {
                                let pav = pavModel.clone();
                                pav.set(p)
                                return pav
                            })
                        };
                    });

                    otherGroupPavsPerModels
                        .forEach((otherGroupPavs, index) => {
                            otherGroupPavs.forEach((otherGroup) => {
                                if (!tmp[otherGroup.key]) {
                                    tmp[otherGroup.key] = {
                                        id: otherGroup.id,
                                        key: otherGroup.key,
                                        label: otherGroup.label,
                                        othersRelationItemsPerModels: [],
                                        currentCollection: []
                                    };
                                }

                                tmp[otherGroup.key].othersRelationItemsPerModels[index] = otherGroup.collection
                                    .map(p => {
                                            let pav = pavModel.clone();
                                            pav.set(p)
                                            return pav
                                        }
                                    );
                            });
                        });

                    this.groupPavsData = Object.values(tmp);

                    this.groupPavsData.map((groupPav, index) => {
                        for (let key = 0; key < this.getOtherModelsCount(); key++) {
                            if (!groupPav.othersRelationItemsPerModels[key]) {
                                this.groupPavsData[index].othersRelationItemsPerModels[key] = [];
                            }
                        }
                    });

                    this.defaultPavModel = pavModel;
                    this.setupRelationship(() => this.wait(false));
                });

            }, this);
        },

        data() {
            return {
                scope: this.scope,
                name: this.relationship.name,
                relationScope: this.relationship.scope,
                columns: this.columns,
                columnLength: this.columns.length + (this.merging ? this.columns.length - 1 : 0),
                attributeList: this.attributeList,
                noData: this.noData,
                merging: this.merging
            };
        },

        setupRelationship(callback) {
            this.buildAttributesData();
            this.groupPavsData.forEach(group => {
                let groupPav = {
                    label: group.label,
                    attributes: []
                }
                this.attributesArr.forEach(attrData => {
                    if (group.id === attrData.attributeGroupId) {
                        groupPav.attributes.push(attrData)
                    }
                });

                this.attributeList.push(groupPav)
            });

            this.attributeList = this.attributeList.filter(p => p.attributes.length)

            this.listenTo(this, 'after:render', () => {
                if (!this.noData && !this.attributeList.length) {
                    this.noData = true;
                    this.reRender();
                }
                this.renderedFields = [];
                this.setupAttributeRecordViews();
            })
            if (callback) {
                callback();
            }
        },

        setupAttributeRecordViews() {
            let totalAttributes = 0;
            this.attributeList.forEach(group => {
                group.attributes.forEach( attrData => {
                    totalAttributes += attrData.others.length + 1;
                });
            });

            this.attributeList.forEach(group => {
                let createView = (attrData, className, callback) => {
                    this.createView(attrData.viewKey, 'pim:views/product-attribute-value/fields/value-container', {
                        el: this.options.el + ` [data-id="${attrData.key}"]  .${className}`,
                        name: "value",
                        nameName: "valueName",
                        model: attrData.pavModel.clone(),
                        mode: (this.merging && (!this.selectedModelId || this.selectedModelId === attrData.modelId)) ? 'edit' : 'detail',
                        inlineEditDisabled: true,
                    }, view => {
                        view.render();
                        if(view.isRendered()) {
                            this.handleAllFieldRender(attrData.key + className, totalAttributes);
                        }

                        view.once('after:render', () => {
                            this.handleAllFieldRender(attrData.key + className, totalAttributes);
                        });

                        this.listenTo(view, 'after:render', () => {
                            if(callback){
                                callback(view);
                            }
                        });
                    });
                }
                group.attributes.forEach(attrData => {
                    createView(attrData, 'current');
                    attrData.others.forEach((data, index) => {
                        data.key =attrData.key;
                        createView(data, 'other'+index, (view) => this.updateBaseUrl(view, attrData.instanceUrl));
                    });
                });
            });
        },

        buildAttributesData() {
            let result = {};

            let buildAttrData = (groupPav, pav) => {
                let pavOthers = [];
                let attributeId = pav.get('attributeId');
                let attrKey = pav.get('attributeId') + '_' + pav.get('channelId') + '_' + pav.get('language');
                for (let i = 0; i < this.getOtherModelsCount(); i++) {
                    let pavModel = groupPav.othersRelationItemsPerModels[i].find(item =>
                        item.get('attributeId') === attributeId && item.get('channelId') === pav.get('channelId') && item.get('language') === pav.get('language')
                    );
                    if (!pavModel) {
                        pavModel = pav.clone();
                        pavModel.set('id', null);
                        pavModel.set('value', null);
                        pavModel.set('valueId', null);
                        pavModel.set('valueName', null);
                        pavModel.set('valueIds', null);
                        pavModel.set('valueNames', null);
                        pavModel.set('valueUnitId', null);
                        pavModel.set('valueUnitName', null);
                    }
                    pavOthers.push(pavModel);
                }

                let label = pav.get('attributeName');

                if (pav.get('language') && pav.get('language') !== 'main') {
                    label += ' / ' + this.translate(pav.get('language'))
                }

                if (pav.get('channelId')) {
                    label += ' / ' + pav.get('channelName');
                }

                if(pav.get('isRequired')) {
                    label += '*';
                }

                return {
                    key: attrKey,
                    modelId: this.models[0].id,
                    attributeGroupId: groupPav.id,
                    label: label,
                    language: pav.get('language'),
                    attributeId: attributeId,
                    channelId: pav.get('channelId'),
                    productAttributeId: pav.get('id'),
                    shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(pav.get('attributeType')) && pav.get('value'),
                    showQuickCompare: true,
                    viewKey: attrKey + 'Current',
                    others: pavOthers.map((model, index) => {
                        return {
                            viewKey: attrKey + 'Other' + index,
                            modelId: this.models[index + 1].id,
                            shouldNotCenter: ['text', 'wysiwyg', 'markdown'].includes(pav.get('attributeType')) && model.get('value'),
                            pavModel: model,
                            index,
                        }
                    }),
                    different: !this.areAttributeEquals(pav, pavOthers),
                    pavModel: pav,
                }
            };

            this.groupPavsData.forEach(groupPav => {
                groupPav.currentCollection.forEach(pav => {
                    let attrKey = pav.get('attributeId') + '_' + pav.get('channelId') + '_' + pav.get('language');

                    result[attrKey] = buildAttrData(groupPav, pav);
                });

                groupPav.othersRelationItemsPerModels.forEach((pavsInInstance, index) => {
                    pavsInInstance.forEach(pav => {
                        let attrKey = pav.get('attributeId') + '_' + pav.get('channelId') + '_' + pav.get('language');

                        if (result[attrKey]) {
                            return;
                        }

                        pavModel = pav.clone();
                        pavModel.set('id', null);
                        pavModel.set('value', null);
                        pavModel.set('valueId', null);
                        pavModel.set('valueName', null);
                        pavModel.set('valueIds', null);
                        pavModel.set('valueNames', null);
                        pavModel.set('valueUnitId', null);
                        pavModel.set('valueUnitName', null);

                        result[attrKey] = buildAttrData(groupPav, pavModel);
                    });
                });
            });
            this.attributesArr = Object.values(result);
        },

        areAttributeEquals(pavCurrent, pavOthers) {
            let compareResult = true;
            for (const pavOther of pavOthers) {
                compareResult = compareResult && JSON.stringify(this.getComparableAttributeData(pavCurrent)) === JSON.stringify(this.getComparableAttributeData(pavOther));
                if (!compareResult) {
                    break;
                }
            }
            return compareResult;
        },

        getComparableAttributeData(model) {
            let attributes = {};
            for (let comparableAttribute of this.comparableAttributeFields) {
                attributes[comparableAttribute] = model.get(comparableAttribute)
            }
            return attributes;
        },

        getOtherModelsCount() {
            return this.collection.models.length - 1;
        },

        afterRender() {
            if (this.merging) {
                $('input[data-id="' + (this.selectedModelId ?? this.models[0].id) + '"]').prop('checked', true);
            }
        },

        updateFieldState(attrData, modelId) {
            let changeMode = (viewKey, pavModel, newMode) => {
                let view = this.getView(viewKey);
                if (!view) {
                    return;
                }
                if (view.mode !== newMode) {
                    view.setMode(newMode);
                    view.model = pavModel;
                    view.reRender();
                }
            }
            let getNewMode = (data) => data.modelId === modelId ? 'edit' : 'detail';
            changeMode(attrData.viewKey, attrData.pavModel.clone(), getNewMode(attrData));
            attrData.others.forEach(data => {
                changeMode(data.viewKey, data.pavModel.clone(), getNewMode(data));
            });
        },

        handleAllFieldRender(key, totalAttributes) {
            if(!this.renderedFields.includes(key)){
                this.renderedFields.push(key);

                if (this.renderedFields.length === totalAttributes) {
                    this.trigger('all-fields-rendered');
                    this.$el.find('input[type="radio"]').prop('disabled', false);
                }
            }
        },

        fetch() {
           let toUpsert = []
            let fetchData = (attrData, viewKey) => {
                let pavAttr = {
                    productId: this.selectedModelId,
                    attributeId: attrData.attributeId,
                    channelId: attrData.channelId ?? '',
                    language: attrData.language
                }
                let view = this.getView(viewKey);

                if(!view){
                    return;
                }

                pavAttr = _.extend({}, pavAttr, view.fetch());

                toUpsert.push(pavAttr);
            };

            this.attributeList.forEach(group => {
                group.attributes.forEach(attrData => {
                    let modelId = this.$el.find(`input.field-radio[name=${attrData.key}]:checked`).val();

                    if(modelId === attrData.modelId) {
                        fetchData(attrData , attrData.viewKey);
                        return;
                    }

                    attrData.others.forEach(data => {
                        if(data.modelId === modelId) {
                            fetchData(attrData, data.viewKey);
                        }
                    });
                });
            });

            return {
                toUpsert,
                scope: 'ProductAttributeValue',
                toDelete: []
            };
        },

        validate() {
            let validate = false;
            let checkValidate = (attrData, viewKey) => {

                let view = this.getView(viewKey);

                if(!view){
                    return;
                }

               validate = validate || view.validate();
            };

            this.attributeList.forEach(group => {
                group.attributes.forEach(attrData => {
                    let modelId = this.$el.find(`input.field-radio[name=${attrData.key}]:checked`).val();

                    if(modelId === attrData.modelId) {
                        checkValidate(attrData , attrData.viewKey);
                        return;
                    }

                    attrData.others.forEach(data => {
                        if(data.modelId === modelId) {
                            checkValidate(attrData, data.viewKey);
                        }
                    });
                });
            });

            return validate;
        }
    })
})