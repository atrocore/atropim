

Espo.define('pim:views/category/list', 'pim:views/list', function (Dep) {
    return Dep.extend({

        afterRender() {
            this.collection.isFetched = false;
            this.clearView('list');
            Dep.prototype.afterRender.call(this);
        }

    });
});
