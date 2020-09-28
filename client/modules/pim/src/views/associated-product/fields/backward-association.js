

Espo.define('pim:views/associated-product/fields/backward-association', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive', 'notUsedAssociations'],

        boolFilterData: {
            notUsedAssociations() {
                return {mainProductId: this.model.get('mainProductId'), relatedProductId: this.model.get('relatedProductId')};
            }
        },

    })
);
