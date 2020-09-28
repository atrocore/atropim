

Espo.define('pim:views/product/fields/brand', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive'],

    })
);