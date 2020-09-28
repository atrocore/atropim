

Espo.define('pim:views/category/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        notSavedFields: ['image'],

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', () => this.model.fetch());
        },

        save(callback, skipExit) {
            (this.notSavedFields || []).forEach(field => {
                const keys = this.getFieldManager().getAttributeList(this.model.getFieldType(field), field);
                keys.forEach(key => delete this.model.attributes[key]);
            });

            return Dep.prototype.save.call(this, callback, skipExit);
        },

    })
);

