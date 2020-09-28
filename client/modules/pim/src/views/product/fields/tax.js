

Espo.define('pim:views/product/fields/tax', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive'],

    })
);