

Espo.define('pim:views/fields/varchar-with-pattern', 'views/fields/varchar',
    Dep => Dep.extend({

        validationPattern: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.utils.clone(this.validations);
            this.validations.push('pattern');
        },

        validatePattern() {
            if (this.validationPattern) {
                let regexp = new RegExp(this.validationPattern);
                let value = this.model.get(this.name);
                if (value !== '' && !regexp.test(value)) {
                    let msg = this.getPatternValidationMessage();
                    if (msg) {
                        this.showValidationMessage(msg);
                    }
                    return true;
                }
            }
            return false;
        },

        getPatternValidationMessage() {
            return null;
        }

    })
);
