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
        nonComparableAttributeFields: ['createdAt','modifiedAt','createdById','createdByName','modifiedById','modifiedByName','sortOrder'],
        data(){
            let data = Dep.prototype.data.call(this);
            data['attributeList'] = this.attributeList;
            return data;
        },
        setupRelationship(callback){
            this.buildAttributesData();
            this.ajaxGetRequest('AttributeGroup',{
                sortBy:'sortOrder',
                where:[
                    {
                        "type": "isLinked",
                        "attribute":"attributes"
                    }
                ]
            }).success(res => {
                this.attributeList = [];
                let groups =  res.list;

                groups.push({
                    "id": null,
                    "name": this.translate("No Group", 'Product')
                })

                groups.forEach(group => {
                    let groupPav = {
                        label: group.name,
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
            });
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
            const currentAttributeIds =  this.currentItemModels.map(pav =>pav.get('attributeId'))
            const otherPavAttributeIds =  this.otherItemModels.map(instancePavs => instancePavs.map(pav =>pav.get('attributeId'))).flat(1)

            const allAttributeIds = Array.from(new Set([...currentAttributeIds, ...otherPavAttributeIds]))
            allAttributeIds.forEach((attributeId) =>{
                if(!attributeId) return;
                const pavCurrent =  this.currentItemModels.filter((v) => v.get('attributeId') === attributeId)[0] ?? {};
                const pavOthers = this.otherItemModels.map(instancePavs => instancePavs.filter((v) => v.get('attributeId') === attributeId)[0] ?? {});
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
                    others: this.otherItemModels.map((model,index) => {
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