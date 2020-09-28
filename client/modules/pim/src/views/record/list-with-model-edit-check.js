

Espo.define('pim:views/record/list-with-model-edit-check', 'views/record/list',
    Dep => Dep.extend({

        buildRow: function (i, model, callback) {
            var key = model.id;

            this.rowList.push(key);
            this.getInternalLayout(function (internalLayout) {
                internalLayout = Espo.Utils.cloneDeep(internalLayout);
                this.prepareInternalLayout(internalLayout, model);

                this.createView(key, 'views/base', {
                    model: model,
                    acl: {
                        edit: model.get('isEditable') && this.getAcl().checkModel(model, 'edit')
                    },
                    el: this.options.el + ' .list-row[data-id="'+key+'"]',
                    optionsToPass: ['acl'],
                    noCache: true,
                    _layout: {
                        type: this._internalLayoutType,
                        layout: internalLayout
                    },
                    name: this.type + '-' + model.name,
                    setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered()
                }, callback);
            }.bind(this), model);
        },

    })
);