

Espo.define('pim:views/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        setup() {
            this.bottomView = this.getMetadata().get(`clientDefs.${this.scope}.bottomView.${this.type}`) || this.bottomView;

            Dep.prototype.setup.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let parentView = this.getParentView();
            if (parentView.options.params && parentView.options.params.setEditMode) {
                this.actionEdit();
            }
        },
    })
);

