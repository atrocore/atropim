/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/product-attribute-values','views/record/compare/relationship', function (Dep) {
    return Dep.extend({
        template:'pim:product/record/compare/product-attribute-values',
        attributesArr: [],
        attributeList:[],
        tabId:null,
        model: null,
        groupPavsData: [],
        defaultPavModel: null,
        noData : false,
        comparableAttributeFields: ['value','valueId', 'attributeId', 'channel'],
        setup(){
            this.tabId = this.options.defs?.tabId;
            Dep.prototype.setup.call(this);
            this.attributeList = [];
            this.groupPavsData = [];
            this.attributesArr = [];
            this.noData = false;
            this.defaultPavModel = null;
        },
        fetchModelsAndSetup(){
            this.wait(true)
            this.getModelFactory().create('ProductAttributeValue', function (model) {
                let param = {
                    tabId: this.tabId,
                    productId: this.baseModel.get('id'),
                    fieldFilter: ['allValues'],
                    languageFilter: ['allLanguages'],
                    scopeFilter: ['allChannels']
                };
                this.ajaxGetRequest('ProductAttributeValue/action/groupsPavs', param).success(res => {
                    let currentGroupPavs = res;
                    let tmp = {}
                    this.ajaxPostRequest('Synchronization/action/distantInstanceRequest',{
                        'uri': 'ProductAttributeValue/action/groupsPavs?'+ $.param(param)
                    }).success(res => {
                        let otherGroupPavsPerInstances = res;
                        currentGroupPavs.forEach((group) => {
                            tmp[group.key] = {
                                id: group.id,
                                key: group.key,
                                label: group.label,
                                othersCollectionItemsPerInstance:[],
                                currentCollection: group.collection.map(p => {
                                    let pav = model.clone();
                                    pav.set(p)
                                    return pav
                                })
                            };
                        })

                        otherGroupPavsPerInstances
                            .forEach((otherGroupPavs,index) => {
                                if('_error' in otherGroupPavs){
                                    this.instances[index]['_error'] = otherGroupPavs['_error'];
                                    return;
                                }
                               otherGroupPavs.forEach((otherGroup) => {
                                   if(!tmp[otherGroup.key]){
                                       tmp[otherGroup.key] = {
                                           id: otherGroup.id,
                                           key: otherGroup.key,
                                           label: otherGroup.label,
                                           othersCollectionItemsPerInstance:[],
                                           currentCollection:[]
                                       };
                                   }

                                   tmp[otherGroup.key].othersCollectionItemsPerInstance[index] = otherGroup.collection
                                       .map(p => {
                                           for(let key in p){
                                               let el = p[key];
                                               let instanceUrl = this.instances[index].atrocoreUrl;
                                               if(key.includes('PathsData')){
                                                   if( el && ('thumbnails' in el)){
                                                       for (let size in el['thumbnails']){
                                                           p[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                                                       }
                                                   }
                                               }
                                           }
                                           let pav = model.clone();
                                           pav.set(p)
                                           return pav
                                       }
                                   )
                               })
                            })
                        this.groupPavsData = Object.values(tmp);
                        this.groupPavsData.map((groupPav, index) =>{
                            this.instances.forEach((instance, key) => {
                                if(!groupPav.othersCollectionItemsPerInstance[key]){
                                    this.groupPavsData[index].othersCollectionItemsPerInstance[key] = [];
                                }
                            })
                        })
                        this.defaultPavModel = model;
                        this.setupRelationship(() => this.wait(false));
                    })
                });
            }, this);
        },
        data(){
            return  {
                name: this.relationship.name,
                scope: this.relationship.scope,
                instances: this.instances,
                attributeList: this.attributeList,
                noData: this.noData
            };
        },
        setupRelationship(callback){
            this.buildAttributesData();
            this.groupPavsData.forEach(group => {
                let groupPav = {
                    label: group.label,
                    attributes: []
                }
                this.attributesArr.forEach(attrData => {
                    if(group.id ===  attrData.attributeGroupId){
                        groupPav.attributes.push(attrData)
                    }
                });

                this.attributeList.push(groupPav)
            });

            this.attributeList = this.attributeList.filter(p => p.attributes.length)
            this.listenTo(this, 'after:render', () => {
                if(!this.noData && !this.attributeList.length){
                    this.noData = true;
                    this.reRender();
                }
                this.setupAttributeRecordViews();
            })
            if(callback){
                callback();
            }
        },
        setupAttributeRecordViews(){
            this.attributeList.forEach( group => {
                group.attributes.forEach(attrData => {
                    let attributeId = attrData.attributeId;
                    let pavModelCurrent = attrData.pavModelCurrent;
                    let pavModelOthers = attrData.pavModelOthers;

                    this.createView( attributeId + 'Current', 'pim:views/product-attribute-value/fields/value-container', {
                        el: this.options.el + ` [data-id="${attributeId}"]  .current`,
                        name: "value",
                        nameName:"valueName",
                        model: pavModelCurrent,
                        readOnly: true,
                        mode: 'list',
                        inlineEditDisabled: true,
                    }, view => view.render());

                    pavModelOthers.forEach((pavModelOther,index) => {
                        this.createView(attributeId + 'Other'+index, 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ` [data-id="${attributeId}"]  .other`+index,
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
        buildAttributesData(){
            let result = {};

            this.groupPavsData.forEach(groupPav => {
                groupPav.currentCollection.forEach(pav => {
                    let pavOthers = [];
                    let attributeId = pav.get('attributeId')
                    this.instances.forEach((i, key) => {
                        pavOthers.push(groupPav.othersCollectionItemsPerInstance[key].find(item =>item.get('attributeId') === attributeId) ?? this.defaultPavModel.clone())
                    })
                    result[attributeId] =  {
                        attributeGroupId: groupPav.id,
                        attributeName: pav.get('attributeName'),
                        attributeChannel: pav.get('channelName') ?? this.translate('Global'),
                        language: pav.get('language'),
                        attributeId: attributeId,
                        productAttributeId: pav.get('id'),
                        showQuickCompare: true,
                        current: attributeId + 'Current',
                        others: pavOthers.map((model,index) => {
                            return { other: attributeId + 'Other'+index, index}
                        }),
                        different:  !this.areAttributeEquals(pav, pavOthers),
                        pavModelCurrent: pav,
                        pavModelOthers: pavOthers,
                    }
                })

                groupPav.othersCollectionItemsPerInstance.forEach((pavsInInstance, index) => {
                    pavsInInstance.forEach(pav => {
                        let attributeId = pav.get('attributeId')
                        if(result[attributeId]){
                            return;
                        }
                        let pavCurrent = this.defaultPavModel.clone();
                        let pavOthers = [];
                        this.instances.forEach((i, key) => {
                            pavOthers.push(groupPav.othersCollectionItemsPerInstance[key].find(item =>item.get('attributeId') === attributeId) ?? this.defaultPavModel.clone())
                        })
                        result[attributeId] =  {
                            attributeGroupId: groupPav.id,
                            attributeName: pav.get('attributeName'),
                            attributeChannel: pav.get('channelName') ?? this.translate('Global'),
                            language: pav.get('language'),
                            attributeId: attributeId,
                            productAttributeId: pav.get('id'),
                            showQuickCompare: true,
                            current: attributeId + 'Current',
                            others: pavOthers.map((model,index) => {
                                return { other: attributeId + 'Other'+index, index}
                            }),
                            different:  !this.areAttributeEquals(pavCurrent, pavOthers),
                            pavModelCurrent: this.defaultPavModel.clone(),
                            pavModelOthers: pavOthers,
                            instanceUrl: this.instances[index].atrocoreUrl
                        }
                    });
                });
            });
            this.attributesArr = Object.values(result);
        },
        areAttributeEquals(pavCurrent, pavOthers){
            let compareResult = true;
            for (const pavOther of pavOthers) {
                compareResult = compareResult && JSON.stringify(this.getComparableAttributeData(pavCurrent)) === JSON.stringify(this.getComparableAttributeData(pavOther));
                if(!compareResult){
                    break;
                }
            }
            return compareResult;
        },
        getComparableAttributeData(model){
            let attributes = {};
            for (let comparableAttribute of this.comparableAttributeFields){
                attributes[comparableAttribute] = model.get(comparableAttribute)
            }
            return attributes;
        }
    })
})