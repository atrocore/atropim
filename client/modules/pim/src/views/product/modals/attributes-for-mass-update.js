Espo.define('pim:views/product/modals/attributes-for-mass-update', 'views/modal', function (Dep) {

    return Dep.extend({
        template: 'modals/edit',

        setup() {
            Dep.prototype.setup.call(this);

            this.buttonList = [
                {
                    name: 'addAttribute',
                    label: 'Add',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.scope || this.options.scope;
            this.layoutName = this.options.layoutName || 'detailSmall';

            this.waitForView('edit');

            this.getModelFactory().create(this.scope, function (model) {
                if (this.options.attributes) {
                    model.set(this.options.attributes);
                }

                this.model = model;

                this.createRecordView(model);
            }.bind(this));

            this.header = this.translate('Product', 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update') + ' &raquo ' + this.getLanguage().translate(this.scope, 'scopeNamesPlural');
        },

        createRecordView: function (model, callback) {
            var viewName = this.getMetadata().get(['clientDefs', model.name, 'recordViews', 'editSmall']) || 'views/record/edit-small';

            var options = {
                model: model,
                el: this.containerSelector + ' .edit-container',
                type: 'editSmall',
                layoutName: this.layoutName,
                columnCount: this.columnCount,
                buttonsDisabled: true,
                sideDisabled: true,
                bottomDisabled: true,
                exit: function () {}
            };
            this.createView('edit', viewName, options, callback);
        },

        actionAddAttribute() {
            let editView = this.getView('edit');

            if (editView.validate()) {
                return false;
            }

            this.trigger('add-attribute', editView.model);
        }
    })
});
