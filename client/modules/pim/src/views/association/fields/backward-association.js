

Espo.define('pim:views/association/fields/backward-association', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive'],

    })
);
