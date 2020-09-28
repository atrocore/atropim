

Espo.define('pim:views/product-family/fields/product-family-template', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        createDisabled: true,

        selectBoolFilterList:  ['onlyActive'],

    })
);
