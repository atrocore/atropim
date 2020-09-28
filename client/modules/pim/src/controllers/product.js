

Espo.define('pim:controllers/product', 'controllers/record', Dep => Dep.extend({

    defaultAction: 'list',

    beforePlate() {
        this.handleCheckAccess('read');
    },

    plate() {
        this.getCollection(function (collection) {
            this.main(this.getViewName('plate'), {
                scope: this.name,
                collection: collection
            });
        });
    },

    list(options) {
        var callback = options.callback;
        var isReturn = options.isReturn;
        if (this.getRouter().backProcessed) {
            isReturn = true;
        }

        var key = this.name + 'List';

        if (!isReturn) {
            var stored = this.getStoredMainView(key);
            if (stored) {
                this.clearStoredMainView(key);
            }
        }

        this.getCollection(function (collection) {
            this.listenToOnce(this.baseController, 'action', function () {
                collection.abortLastFetch();
            }, this);

            this.main(this.getViewName('list'), {
                scope: this.name,
                collection: collection,
                params: options
            }, callback, isReturn, key);
        }, this, false);
    }

}));
