

Espo.define('pim:views/product-type-package/grid', 'views/base',
    Dep => Dep.extend({

        template: 'pim:product-type-package/grid',

        mode: 'detail',

        layoutFields: ['measuringUnit', 'content', 'basicUnit', 'packingUnit'],

        data() {
            return {
                layoutFields: this.layoutFields
            };
        },

        afterRender() {
            this.buildGrid();

            Dep.prototype.afterRender.call(this);
        },

        buildGrid() {
            if (this.nestedViews) {
                for (let child in this.nestedViews) {
                    this.clearView(child);
                }
            }

            let mode = this.getDetailViewMode();

            this.layoutFields.forEach(field => {
                let viewName = this.model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(this.model.getFieldType(field));
                this.createView(field, viewName, {
                    mode: mode,
                    inlineEditDisabled: true,
                    model: this.model,
                    el: this.options.el + ` .field[data-name="${field}"]`,
                    defs: {
                        name: field,
                    }
                }, view => view.render());
            });
        },

        getDetailViewMode() {
            let mode = 'detail';
            let parentView = this.getParentView();
            if (parentView) {
                let detailView = this.getParentView().getDetailView();
                if (detailView) {
                    mode = detailView.mode;
                }
            }
            return mode;
        }

    })
);
