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
        attributesPanelsView: 'pim:views/product/record/compare/attributes-panels',
        pavModel: null,
        nonComparableAttributeFields: ['createdAt','modifiedAt','createdById','createdByName','modifiedById','modifiedByName','sortOrder'],
        attributesArr:[],
        setup(){
            this.getModelFactory().create('ProductAttributeValue', function(pavModel){
                this.pavModel = pavModel;
                Dep.prototype.setup.call(this)
                this.fieldsArr = this.fieldsArr.filter((el) => el.field !== 'productAttributeValues');
            }, this)
        },

        setupAttributesPanels() {
            this.createView('attributesPanels', this.attributesPanelsView, {
                scope: this.scope,
                attributesArr: this.attributesArr,
                distantModels: this.distantModelsAttribute,
                model: this.model,
                el: `${this.options.el} .compare-panel[data-name="attributesPanels"]`
            }, view => view.render())
        },
        afterModelsLoading(modelCurrent, modelOthers) {

            this.notify(true)
            const currentAttributeIds =  modelCurrent.get('productAttributeValues').map(pav =>pav.attributeId)
            const otherPavAttributeIds =  modelOthers.map(model => model.get('productAttributeValues').map(pav =>pav.attributeId)).flat(1)

            const allAttributeIds = Array.from(new Set([...currentAttributeIds, ...otherPavAttributeIds]));
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

                this.attributesArr.push({
                    attributeName: attributeName,
                    attributeChannel: attributeChannel,
                    language: language,
                    attributeId: attributeId,
                    productAttributeId: productAttributeId,
                    showQuickCompare: true,
                    current: attributeId + 'Current',
                    others: pavModelOthers.map((model,index) => {
                       return { other: attributeId + 'Other'+index, index}
                    }),
                    different:  !this.areAttributeEquals(pavCurrent, pavOthers),
                    pavModelCurrent: pavModelCurrent,
                    pavModelOthers: pavModelOthers
                });

              this.notify(false)
            })

            this.listenTo(this, 'after:render', () => {
                this.setupAttributesPanels();
            });
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