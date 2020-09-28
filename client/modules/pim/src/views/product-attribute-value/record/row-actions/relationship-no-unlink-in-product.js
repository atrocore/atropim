

Espo.define('pim:views/product-attribute-value/record/row-actions/relationship-no-unlink-in-product', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        pipelines: {
            actionListPipe: ['clientDefs', 'ProductAttributeValue', 'actionListPipe']
        },

        getActionList() {
            let list = [{
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            }];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        },
                        link: '#' + this.model.name + '/edit/' + this.model.id
                    }
                ]);
            }

            if (this.options.acl.delete && this.model.get('isCustom')) {
                list.push({
                    action: 'removeRelated',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }
            this.runPipeline('actionListPipe', list);
            return list;
        }

    })
);
