

Espo.define('pim:views/product-family/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        manageAccessDelete() {
            Dep.prototype.manageAccessDelete.call(this);
            if (this.model.get('isSystem')) {
                this.hideActionItem('delete');
            }
        }

    })
);