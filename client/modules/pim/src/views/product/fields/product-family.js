

Espo.define('pim:views/product/fields/product-family', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive'],

    })
);