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

        setup() {
            this.tabId = this.options.defs?.tabId;

            Dep.prototype.setup.call(this);

            this.attributeList = [];
            this.groupPavsData = [];
            this.attributesArr = [];
            this.noData = false;
            this.defaultPavModel = null;
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
                        this.getOthersList().forEach((m, key) => {
                            if (!groupPav.othersRelationItemsPerModels[key]) {
                                this.groupPavsData[index].othersRelationItemsPerModels[key] = [];
                            }
                        })
                    })

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
                columnLength: this.columns.length,
                columnLength1: this.columns.length - 1,
                attributeList: this.attributeList,
                noData: this.noData
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
                this.setupAttributeRecordViews();
            })
            if (callback) {
                callback();
            }
        },

        setupAttributeRecordViews() {
            this.attributeList.forEach(group => {
                group.attributes.forEach(attrData => {
                    let attrKey = attrData.key;
                    let pavModelCurrent = attrData.pavModelCurrent;
                    let pavModelOthers = attrData.pavModelOthers;

                    this.createView(attrKey + 'Current', 'pim:views/product-attribute-value/fields/value-container', {
                        el: this.options.el + ` [data-id="${attrKey}"]  .current`,
                        name: "value",
                        nameName: "valueName",
                        model: pavModelCurrent,
                        readOnly: true,
                        mode: 'list',
                        inlineEditDisabled: true,
                    }, view => view.render());

                    pavModelOthers.forEach((pavModelOther, index) => {
                        this.createView(attrKey + 'Other' + index, 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ` [data-id="${attrKey}"]  .other` + index,
                            name: "value",
                            model: pavModelOther,
                            readOnly: true,
                            mode: 'list',
                            inlineEditDisabled: true,
                        }, view => {
                            view.render()
                            this.updateBaseUrl(view, attrData.instanceUrl)
                        });
                    })
                })
            })
        },

        buildAttributesData() {
            let result = {};

            this.groupPavsData.forEach(groupPav => {
                groupPav.currentCollection.forEach(pav => {
                    let pavOthers = [];
                    let attributeId = pav.get('attributeId')
                    let attrKey = pav.get('attributeId') + pav.get('channelId') + pav.get('language')
                    this.getOthersList().forEach((i, key) => {
                        pavOthers.push(groupPav.othersRelationItemsPerModels[key].find(item =>
                            item.get('attributeId') === attributeId && item.get('channelId') === pav.get('channelId') && item.get('language') === pav.get('language')
                        ) ?? this.defaultPavModel.clone())
                    })
                    result[attrKey] = {
                        key: attrKey,
                        attributeGroupId: groupPav.id,
                        attributeName: pav.get('attributeName'),
                        attributeChannel: pav.get('channelName') ?? this.translate('Global'),
                        language: pav.get('language'),
                        attributeId: attributeId,
                        channelId: pav.get('channelId'),
                        productAttributeId: pav.get('id'),
                        showQuickCompare: true,
                        current: attrKey + 'Current',
                        others: pavOthers.map((model, index) => {
                            return {other: attrKey + 'Other' + index, index}
                        }),
                        different: !this.areAttributeEquals(pav, pavOthers),
                        pavModelCurrent: pav,
                        pavModelOthers: pavOthers,
                    }
                })

                groupPav.othersRelationItemsPerModels.forEach((pavsInInstance, index) => {
                    pavsInInstance.forEach(pav => {
                        let attributeId = pav.get('attributeId')
                        let attrKey = pav.get('attributeId') + pav.get('channelId') + pav.get('language')

                        if (result[attrKey]) {
                            return;
                        }

                        let pavCurrent = this.defaultPavModel.clone();
                        let pavOthers = [];
                        this.getOthersList().forEach((i, key) => {
                            pavOthers.push(groupPav.othersRelationItemsPerModels[key].find(item =>
                                    item.get('attributeId') === attributeId && item.get('channelId') === pav.get('channelId') && item.get('language') === pav.get('language')
                                )
                                ?? this.defaultPavModel.clone())
                        })
                        result[attrKey] = {
                            key: attrKey,
                            attributeGroupId: groupPav.id,
                            attributeName: pav.get('attributeName'),
                            attributeChannel: pav.get('channelName') ?? this.translate('Global'),
                            language: pav.get('language'),
                            attributeId: attributeId,
                            channelId: pav.get('channelId'),
                            productAttributeId: pav.get('id'),
                            showQuickCompare: true,
                            current: attrKey + 'Current',
                            others: pavOthers.map((model, index) => {
                                return {other: attrKey + 'Other' + index, index}
                            }),
                            different: !this.areAttributeEquals(pavCurrent, pavOthers),
                            pavModelCurrent: this.defaultPavModel.clone(),
                            pavModelOthers: pavOthers,
                            instanceUrl: this.instances[index]?.atrocoreUrl
                        }
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

        getOthersList() {
            return this.collection.filter(m => m.id !== this.baseModel.id);
        }
    })
})