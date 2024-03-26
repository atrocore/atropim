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
        pavModel: null,
        nonComparableAttributeFields: ['createdAt','modifiedAt','createdById','createdByName','modifiedById','modifiedByName','sortOrder'],
        setup(){
            this.getModelFactory().create('ProductAttributeValue', function(pavModel){
                this.pavModel = pavModel;
                Dep.prototype.setup.call(this)
            }, this)
        },
        addCustomRows(modelCurrent, modelOthers) {
            this.notify(true)
            const currentAttributeIds =  modelCurrent.get('productAttributeValues').map(pav =>pav.attributeId)
            const otherPavAttributeIds =  modelOthers.map(model => model.get('productAttributeValues').map(pav =>pav.attributeId)).flat(1)

            const allAttributeIds = Array.from(new Set([...currentAttributeIds, ...otherPavAttributeIds]));
            if(allAttributeIds.length > 0){
                this.fieldsArr.push({
                    separator:true
                });
            }
            allAttributeIds.forEach((attributeId) =>{
                const pavCurrent = modelCurrent.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {};
                const pavOthers = modelOthers.map(modelOther => modelOther.get('productAttributeValues').filter((v) => v.attributeId === attributeId)[0] ?? {});
                const pavModelCurrent = this.pavModel.clone();
                const attributeName = pavCurrent.attributeName ?? pavOthers.map(pavOthers => pavOthers.attributeName).reduce((prev, curr)  => prev ?? curr);
                const attributeChannel = pavCurrent.channelName ?? pavOthers.map(pavOthers => pavOthers.channelName).reduce((prev, curr)  => prev ?? curr);
                const productAttributeId = pavCurrent.id ?? pavOthers.map(pavOthers => pavOthers.id).reduce((prev, curr)  => prev ?? curr);
                const language = pavCurrent.language ?? pavOthers.map(pavOthers => pavOthers.language).reduce((prev, curr)  => prev ?? curr);
                const pavModelOthers = pavOthers.map(pavOther => {
                    const pavModel = this.pavModel.clone();
                    pavModel.set(pavOther);
                    return pavModel
                });

                pavModelCurrent.set(pavCurrent);

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

                this.fieldsArr.push({
                    isField: false,
                    attributeName: attributeName,
                    attributeChannel: attributeChannel,
                    language: language,
                    attributeId: attributeId,
                    productAttributeId: productAttributeId,
                    canQuickCompare: false,
                    current: attributeId + 'Current',
                    others: pavModelOthers.map((model,index) => {
                       return { other: attributeId + 'Other'+index, index}
                    }),
                    different:  !this.areAttributeEquals(pavCurrent, pavOthers)
                });

              this.notify(false)

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
        },

    })
)