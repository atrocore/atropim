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
        nonComparableAttributeFields: ['createdAt','modifiedAt','createdById','createdByName','modifiedById','modifiedByName','sortOrder'],
        setup(){
            this.tabId = this.options.defs?.tabId;
            Dep.prototype.setup.call(this);
            this.attributeList = [];
            this.groupPavsData = [];
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
                    this.ajaxGetRequest('Synchronization/action/distantInstanceRequest',{
                        'uri': 'ProductAttributeValue/action/groupsPavs?'+ $.param(param)
                    }).success(res => {
                        let otherGroupPavsPerInstances = res;
                        currentGroupPavs.forEach((group) => {
                            let data = {
                                id: group.id,
                                key: group.key,
                                label: group.label,
                                currentCollection: group.collection.map(p => {
                                    let pav = model.clone();
                                    pav.set(p)
                                    return pav
                                })
                            };
                            data.othersCollectionPerInstance = otherGroupPavsPerInstances
                                .map(otherGroupPavs => {
                                    let otherGroup = otherGroupPavs.find(g => g.id === group.id)
                                    if(otherGroup){
                                        return otherGroup.collection.map(p => {
                                            let pav = model.clone();
                                            pav.set(p)
                                            return pav
                                        })
                                    }
                                    return [];
                                })
                            this.groupPavsData.push(data)
                        })
                        this.setupRelationship(() => this.wait(false));
                    })
                });
            }, this);
        },
        data(){
            let data = Dep.prototype.data.call(this);
            data['attributeList'] = this.attributeList;
            return data;
        },
        setupRelationship(callback){
            this.buildAttributesData();
            this.groupPavsData.forEach(group => {
                let groupPav = {
                    label: group.label,
                    attributes: []
                }
                this.attributesArr.forEach(attrData => {
                    if(group.id === attrData.pavModelCurrent.get('attributeGroupId')){
                        groupPav.attributes.push(attrData)
                    }
                });
                this.attributeList.push(groupPav)
            });

            this.attributeList = this.attributeList.filter(p => p.attributes.length)
            this.listenTo(this, 'after:render', () => {
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
                        }, view => view.render());
                    })
                })
            })
        },
        buildAttributesData(){
            let currentItemsModels = this.groupPavsData
                .map(group => group.currentCollection)
                .flat(1);

            let currentAttributeIds =  currentItemsModels
                .map(pav =>pav.get('attributeId'))

            let  otherItemModels = this.groupPavsData
                .map(group => group.othersCollectionPerInstance)
                .flat(1)
            let otherPavAttributeIds =  otherItemModels
                .map(instancePavs => instancePavs.map(pav =>pav.get('attributeId')))
                .flat(1)

            const allAttributeIds = Array.from(new Set([...currentAttributeIds, ...otherPavAttributeIds]))
            allAttributeIds.forEach((attributeId) =>{
                if(!attributeId) return;
                const pavCurrent =  currentItemsModels.filter((v) => v.get('attributeId') === attributeId)[0] ?? {};
                const pavOthers = otherItemModels.map(instancePavs => instancePavs.find((v) => v.get('attributeId') === attributeId)).filter(i => i);
                const attributeName = pavCurrent.get('attributeName') ?? pavOthers.map(pav => pav.get('attributeName')).reduce((prev, curr)  => prev ?? curr);
                const attributeChannel = pavCurrent.get('attributeName') ?? pavOthers.map(pav => pav.get('channelName')).reduce((prev, curr)  => prev ?? curr);
                const productAttributeId = pavCurrent.get('id') ?? pavOthers.map(pav => pav.get('id')).reduce((prev, curr)  => prev ?? curr);
                const language = pavCurrent.language ?? pavOthers.map(pav => pav.get('language')).reduce((prev, curr)  => prev ?? curr);

                this.attributesArr.push({
                    attributeName: attributeName,
                    attributeChannel: attributeChannel,
                    language: language,
                    attributeId: attributeId,
                    productAttributeId: productAttributeId,
                    showQuickCompare: true,
                    current: attributeId + 'Current',
                    others: pavOthers.map((model,index) => {
                        return { other: attributeId + 'Other'+index, index}
                    }),
                    different:  !this.areAttributeEquals(pavCurrent, pavOthers),
                    pavModelCurrent: pavCurrent,
                    pavModelOthers: pavOthers
                });

            })
        },
        areAttributeEquals(pavCurrent, pavOthers){
            for (const nonComparableAttributeField of this.nonComparableAttributeFields) {
                delete pavCurrent[nonComparableAttributeField];
                for (let i = 0; i < pavOthers.length; i++) {
                    delete  pavOthers[i][nonComparableAttributeField];
                }

            }
            let compareResult = true;
            for (const pavOther of pavOthers) {
                compareResult = compareResult && JSON.stringify(pavCurrent) === JSON.stringify(pavOther);
                if(!compareResult){
                    break;
                }
            }
            return compareResult;
        }
    })
})