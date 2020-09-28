

Espo.define('pim:views/category/record/list', 'pim:views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', () => {
                this.listenToOnce(this.collection, 'sync', () => this.reRender());
                this.collection.fetch();
            });
        }

    })
);

