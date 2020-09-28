

Espo.define('pim:views/fields/bool-required', 'views/fields/bool',
    Dep => Dep.extend({

        validations: ['required'],

        validateRequired() {
            if (this.isRequired()) {
                if (!this.model.get(this.name)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate(this.name, 'fields', this.model.name));
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

    })
);

