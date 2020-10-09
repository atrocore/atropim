Espo.define('pim:views/product/record/panels/categories', 'views/record/panels/relationship',
    Dep => Dep.extend({

        actionSelectRelatedEntity(data) {
            setTimeout(function () {
                $('.add-filter[data-name=channels]').click();
            }, 750);
        },

        boolFilterData: {
            onlyCatalogCategories() {
                return this.model.get('catalogId');
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:relate after:unrelate', link => {
                if (link === 'categories') {
                    $('.action[data-action=refresh][data-panel=channels]').click();
                }
            });

            let select = this.actionList.find(item => item.action === (this.defs.selectAction || 'selectRelated'));
            if (select) {
                select.data = {
                    link: this.link,
                    scope: this.scope,
                    boolFilterListCallback: 'getSelectBoolFilterList',
                    boolFilterDataCallback: 'getSelectBoolFilterData',
                    primaryFilterName: this.defs.selectPrimaryFilterName || null
                };
            }
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
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

    })
);