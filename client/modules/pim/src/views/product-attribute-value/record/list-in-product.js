


Espo.define('pim:views/product-attribute-value/record/list-in-product', 'views/record/list',
    Dep => Dep.extend({

        pipelines: {
            actionShowRevisionAttribute: ['clientDefs', 'ProductAttributeValue', 'actionShowRevisionAttribute']
        },

        hiddenInEditColumns: ['isRequired'],

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', model => {
                let panelView = this.getParentView();
                if (panelView && panelView.model) {
                    panelView.model.trigger('after:attributesSave');
                }
            });

            this.runPipeline('actionShowRevisionAttribute');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        prepareInternalLayout(internalLayout, model) {
            Dep.prototype.prepareInternalLayout.call(this, internalLayout, model);

            internalLayout.forEach(item => item.options.mode = this.options.mode || item.options.mode);
        },

        setListMode() {
            this.mode = 'list';
            this.updateModeInFields(this.mode);
        },

        setEditMode() {
            this.mode = 'edit';
            this.updateModeInFields(this.mode);
        },

        updateModeInFields(mode) {
            Object.keys(this.nestedViews).forEach(row => {
                let rowView = this.nestedViews[row];
                if (rowView) {
                    let fieldView = rowView.getView('valueField');
                    if (fieldView && fieldView.model && !fieldView.model.getFieldParam(fieldView.name, 'readOnly')
                        && typeof fieldView.setMode === 'function') {
                        fieldView.setMode(mode);
                        fieldView.reRender();
                    }
                }
            });
        }

    })
);