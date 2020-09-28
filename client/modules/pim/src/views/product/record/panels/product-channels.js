

Espo.define('pim:views/product/record/panels/product-channels', 'views/record/panels/relationship',
    Dep => Dep.extend({
        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.collection.parentEntityId = this.model.get('id');

            this.listenTo(this.collection, 'change:isActiveEntity', model => {
                if (!model.hasChanged('modifiedAt')) {
                    this.notify('Saving...');
                    let value = model.get('isActiveEntity');
                    let data = {entityName: 'Product', value: value, entityId: this.collection.parentEntityId};
                    this.ajaxPutRequest('Channel/' + model.get('id') + '/setIsActiveEntity', data).then(response => {
                        this.notify('Saved', 'success');
                    });
                }
            });
        },
    })
);