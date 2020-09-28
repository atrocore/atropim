

Espo.define('pim:views/associated-product/record/edit-small', 'views/record/edit-small',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let middle = this.getView('middle');
            if (middle && !this.model.isNew()) {
                let bothDirections = middle.getView('bothDirections');
                if (bothDirections) {
                    bothDirections.setReadOnly();
                }
                let backwardAssociation = middle.getView('backwardAssociation');
                if (backwardAssociation) {
                    backwardAssociation.setReadOnly();
                }
            }
        },

    })
);
