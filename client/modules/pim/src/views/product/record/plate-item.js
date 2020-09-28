

Espo.define('pim:views/product/record/plate-item', 'view',
    Dep => Dep.extend({

        template: 'pim:product/record/plate-item',

        fields: ['productStatus', 'image'],

        events: _.extend({
            'click .link': function (e) {
                e.stopPropagation();
                e.preventDefault();
                const url = $(e.currentTarget).attr('href');
                this.getRouter().navigate(url, {trigger: false});
                this.getRouter().dispatch(this.model.name, 'view', {
                    id: this.model.id,
                    rootUrl: `${this.model.name}/plate`,
                    model: this.model
                });
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            if (this.options.rowActionsView) {
                this.waitForView('rowActions');
                this.createView('rowActions', this.options.rowActionsView, {
                    el: `${this.options.el} .actions`,
                    model: this.model,
                    acl: this.options.acl
                });
            }

            this.createFields();
        },

        data() {
            return {

            };
        },

        createFields() {
            this.fields.forEach(field => {
                const view = `${field}Field`;
                const type = this.model.getFieldParam(field, 'type');
                const viewName = this.model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);

                this.waitForView(view);
                this.createView(view, viewName, {
                    el: `${this.options.el} [data-name="${field}"]`,
                    model: this.model,
                    mode: 'list',
                    name: field
                }, () => {
                    if (field === 'image') {
                        this.model.trigger('updateProductImage');
                    }
                });
            });
        }

    })
);

