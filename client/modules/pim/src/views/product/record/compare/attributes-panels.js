/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/attributes-panels','view', function (Dep) {
    return Dep.extend({
        template: 'pim:record/compare/attributes-panels',
        attributeList: [],
        setup(){
            Dep.prototype.setup.call(this)
            this.attributesArr = this.options.attributesArr;
            this.wait(true)
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
                    console.log(group)
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
                    this.attributeList = this.attributeList.filter(p => p.attributes.length)
                });
                this.setupAttributeList();
                this.wait(false)
            });
        },
        data(){
            return {
                scope: this.options.scope,
                attributeList: this.attributeList,
                distantModels: this.options.distantModels
            }
        },
        setupAttributeList(){

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
                    });

                    pavModelOthers.forEach((pavModelOther,index) => {
                        this.createView(attributeId + 'Other'+index, 'pim:views/product-attribute-value/fields/value-container', {
                            el: this.options.el + ` [data-id="${attributeId}"]  .other`+index,
                            name: "value",
                            model: pavModelOther,
                            readOnly: true,
                            mode: 'list',
                            inlineEditDisabled: true,
                        });
                    })
                })
            })
        }
    })
})