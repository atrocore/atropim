

Espo.define('pim:views/asset-relation/fields/channels', 'treo-core:views/fields/filtered-link-multiple',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:scope', () => {
                if (this.model.get('scope') !== 'Channel') {
                    this.model.set({
                        [this.idsName]: [],
                        [this.nameHashName]: {}
                    });
                }
            });
        }
    })
);

