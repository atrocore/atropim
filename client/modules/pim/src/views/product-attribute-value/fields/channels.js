

Espo.define('pim:views/product-attribute-value/fields/channels', 'treo-core:views/fields/filtered-link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: ['notLinkedWithAttributesInProduct'],

        boolFilterData: {
            notLinkedWithAttributesInProduct() {
                return {
                    productId: this.model.get('productId'),
                    attributeId: this.model.get('attributeId')
                };
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attributeId change:scope', () => {
                if (this.model.get('scope') !== 'Channel' || !this.model.get('attributeId')) {
                    this.model.set({
                        [this.idsName]: null,
                        [this.nameHashName]: null
                    });
                }
            });
        }

    })
);

