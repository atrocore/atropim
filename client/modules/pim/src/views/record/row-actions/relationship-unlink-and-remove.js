

Espo.define('pim:views/record/row-actions/relationship-unlink-and-remove', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList() {
            if (this.options.acl.edit) {
                return [
                    {
                        action: 'unlinkRelated',
                        label: 'Unlink',
                        data: {
                            id: this.model.id
                        }
                    },
                    {
                        action: 'removeRelated',
                        label: 'Remove',
                        data: {
                            id: this.model.id
                        }
                    }
                ];
            }
        }

    })
);
