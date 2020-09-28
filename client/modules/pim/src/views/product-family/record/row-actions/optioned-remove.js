

Espo.define('pim:views/product-family/record/row-actions/optioned-remove', 'views/record/row-actions/default',
    Dep=> Dep.extend({

        getActionList: function () {
            var list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                }
            }];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        }
                    }
                ]);

                if (!this.model.get('isSystem')) {
                    list = list.concat([
                        {
                            action: 'quickRemove',
                            label: 'Remove',
                            data: {
                                id: this.model.id
                            }
                        }
                    ]);
                }
            }
            return list;
        },

    })
);


