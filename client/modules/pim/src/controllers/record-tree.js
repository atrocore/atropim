

Espo.define('pim:controllers/record-tree', 'controllers/record-tree',
    Dep => {

    return Dep.extend({

        defaultAction: 'list',

        listTree: function (options) {
            this.getCollection(function (collection) {
                collection.url = collection.name;
                collection.isFetched = true;
                this.main(this.getViewName('listTree'), {
                    scope: this.name,
                    collection: collection
                });
            });
        },
    });
});
