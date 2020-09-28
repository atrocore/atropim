

Espo.define('pim:views/fields/code-from-name', 'pim:views/fields/varchar-with-pattern',
    Dep => Dep.extend({

        validationPattern: '^[a-z_0-9{}\u00de-\u00ff]+$',

        getPatternValidationMessage() {
            return this.translate('fieldHasPattern', 'messages').replace('{field}', this.translate(this.name, 'fields', this.model.name));
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:name', () => {
                if (!this.model.get('code')) {
                    let value = this.model.get('name');
                    if (value) {
                        this.model.set({[this.name]: this.transformToPattern(value)});
                    }
                }
            });
        },

        transformToPattern(value) {
            return value.toLowerCase().replace(/ /g, '_').replace(/[^a-z_0-9\u00de-\u00ff]/gu, '');
        }

    })
);
