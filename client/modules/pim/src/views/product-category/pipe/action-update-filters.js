

Espo.define('pim:views/product-category/pipe/action-update-filters', 'treo-core:pipe',
    Dep => Dep.extend({

        runPipe(data) {
            data = data || {};
            const boolFilterData = data.boolFilterData;

            const catalogsIds = this.options.checkedList.map(id => {
                const model = this.options.mainCollection.get(id);
                return model.get('catalogId');
            });

            _.extend(boolFilterData, {
                onlyCatalogCategories: catalogsIds
            });

            //required
            data.callback();
        },

    })
);