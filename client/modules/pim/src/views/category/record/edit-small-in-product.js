

Espo.define('pim:views/category/record/edit-small-in-product', 'views/record/edit-small',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.hideField('categoryParent');
        }

    })
);

