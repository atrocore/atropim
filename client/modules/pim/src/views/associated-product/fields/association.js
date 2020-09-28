

Espo.define('pim:views/associated-product/fields/association', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive', 'notUsedAssociations'],

        boolFilterData: {
            notUsedAssociations() {
                return {mainProductId: this.model.get('mainProductId'), relatedProductId: this.model.get('relatedProductId')};
            }
        },

        select(model) {
            Dep.prototype.select.call(this, model);

            if (model.get('backwardAssociationId') && !this.model.get('backwardAssociationId')) {
                this.model.set({
                    bothDirections: true,
                    backwardAssociationId: model.get('backwardAssociationId'),
                    backwardAssociationName: model.get('backwardAssociationName')
                });
            }
        }

    })
);
