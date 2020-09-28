

Espo.define('pim:views/record/row-actions/relationship-custom-unlink-and-remove', 'views/record/row-actions/default',
    Dep=> Dep.extend({

        getActionList: function () {
            let list = [];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'unlinkRelatedCustom',
                        label: 'Unlink',
                        data: {
                            id: this.model.id
                        }
                    },
                    {
                        action: 'removeRelatedCustom',
                        label: 'Remove',
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


