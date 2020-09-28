

Espo.define('pim:views/product-category/fields/category', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        createDisabled: true,

        selectBoolFilterList:  ["onlyActive", "onlyCatalogCategories"],

        boolFilterData: {
            onlyCatalogCategories() {
                return this.model.catalogId;
            }
        },

    })
);
