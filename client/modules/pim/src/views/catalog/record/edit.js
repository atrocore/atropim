

Espo.define('pim:views/catalog/record/edit', 'views/record/edit',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.model.get('_duplicatingEntityId')) {
                this.listenTo(this, 'after:save', () => {
                    setTimeout(() => {
                        Backbone.trigger('showQueuePanel');
                    }, 2000);
                });
            }
        },
    })
);

