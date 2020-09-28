

Espo.define('pim:views/associated-product/fields/related-product', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['notEntity', 'notAssociatedProducts'],

        boolFilterData: {
            notEntity() {
                return this.model.get('mainProductId');
            },
            notAssociatedProducts() {
                return {mainProductId: this.model.get('mainProductId'), associationId: this.model.get('associationId')};
            }
        },

    })
);
