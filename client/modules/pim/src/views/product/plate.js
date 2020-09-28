

Espo.define('pim:views/product/plate', 'pim:views/product/list',
    Dep => Dep.extend({

        name: 'plate',

        setup() {
            Dep.prototype.setup.call(this);

            this.collection.maxSize = 20;
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.plate') || 'views/product/record/plate';
        }

    })
);

