

Espo.define('pim:views/packaging/record/panels/products', 'views/record/panels/relationship',
    Dep => Dep.extend({

        boolFilterData: {
            notLinkedWithPackaging() {
                return this.model.id;
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            let select = this.actionList.find(item => item.action === (this.defs.selectAction || 'selectRelated'));

            if (select) {
                select.data.boolFilterListCallback = 'getSelectBoolFilterList';
                select.data.boolFilterDataCallback = 'getSelectBoolFilterData';
            }
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

        getSelectBoolFilterData(boolFilterList) {
            let data = {};
            if (Array.isArray(boolFilterList)) {
                boolFilterList.forEach(item => {
                    if (this.boolFilterData && typeof this.boolFilterData[item] === 'function') {
                        data[item] = this.boolFilterData[item].call(this);
                    }
                });
            }
            return data;
        }
    })
);
