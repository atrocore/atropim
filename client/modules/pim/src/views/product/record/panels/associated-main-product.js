

Espo.define('pim:views/product/record/panels/associated-main-product', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            this.defs.create = this.getAcl().check('Product', 'edit') && !this.defs.readOnly;
            Dep.prototype.setup.call(this);
        }

    })
);
