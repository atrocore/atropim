

Espo.define('pim:views/product/fields/packaging', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive']

    })
);
