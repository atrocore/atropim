

Espo.define('pim:views/category/fields/category-parent', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['notEntity', 'notChildCategory', 'fromCategoryTree'],

        boolFilterData: {
            notEntity() {
                return this.model.id || this.model.get('ids') || [];
            },
            notChildCategory() {
                return this.model.id;
            },
            fromCategoryTree() {
                return this.model.id;
            }
        },

    })
);
