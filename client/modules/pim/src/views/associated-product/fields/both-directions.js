

Espo.define('pim:views/associated-product/fields/both-directions', 'views/fields/bool',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:bothDirections', () => {
                if (!this.model.get('bothDirections')) {
                    this.model.set({
                        backwardAssociationId: null,
                        backwardAssociationName: null
                    });
                }
            });
        }

    })
);
