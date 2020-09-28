

Espo.define('pim:views/product/record/row-actions/custom-edit-and-unlink', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList: function () {
            let list = [];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'customEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        }
                    },
                    {
                        action: 'unlinkRelated',
                        label: 'Unlink',
                        data: {
                            id: this.model.id
                        }
                    }
                ]);
            }
            return list;
        },

    })
);


