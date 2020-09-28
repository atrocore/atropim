

Espo.define('pim:views/product-category/fields/channels', 'treo-core:views/fields/filtered-link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: ['notLinkedWithCategoriesInProduct'],

        boolFilterData: {
            notLinkedWithCategoriesInProduct() {
                return {
                    productId: this.model.get('productId'),
                    categoryId: this.model.get('categoryId')
                };
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:categoryId change:scope', () => {
                if (this.model.get('scope') !== 'Channel' || !this.model.get('categoryId')) {
                    this.model.set({
                        [this.idsName]: null,
                        [this.nameHashName]: null
                    });
                }
            });
        }

    })
);

