

Espo.define('pim:views/product/fields/catalog', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        setup() {
            if (this.mode !== 'search') {
                this.selectBoolFilterList = ['notEntity'];
                this.boolFilterData = {
                    notEntity() {
                        return this.model.get(this.idName);
                    }
                }
            }

            Dep.prototype.setup.call(this);
        }

    })
);
