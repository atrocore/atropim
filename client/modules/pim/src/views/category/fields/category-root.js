

Espo.define('pim:views/category/fields/category-root', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyRootCategory'],

        boolFilterData: {
            onlyRootCategory() {
                return true;
            }
        },

    })
);
