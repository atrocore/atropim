/*
 * This file is part of AtroPIM.
 *
 * AtroPIM - Open Source PIM application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 * Website: https://atropim.com
 *
 * AtroPIM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroPIM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AtroPIM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "AtroPIM" word.
 */

Espo.define('pim:views/product/record/detail', 'pim:views/record/detail',
    Dep => Dep.extend({

        template: 'pim:product/record/detail',

        catalogTreeData: null,

        notSavedFields: ['image'],

        isCatalogTreePanel: false,

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() && (this.type === 'detail' || this.type === 'edit') && this.getMetadata().get(['scopes', this.scope, 'advancedFilters'])) {
                this.listenTo(this.model, 'main-image-updated', () => {
                    this.applyOverviewFilters();
                });
            }

            if (!this.isWide && this.type !== 'editSmall' && this.type !== 'detailSmall'
                && this.getAcl().check('Catalog', 'read') && this.getAcl().check('Category', 'read')) {
                this.isCatalogTreePanel = true;
                this.setupCatalogTreePanel();
            }

            // refresh attributes panel after any saving
            this.listenTo(this.model, 'after:save', () => {
                $(".panel-productAttributeValues button[data-action='refresh']").click();
            });
        },

        setupCatalogTreePanel() {
            this.createView('catalogTreePanel', 'pim:views/product/record/catalog-tree-panel', {
                el: `${this.options.el} .catalog-tree-panel`,
                scope: this.scope,
                model: this.model
            }, view => {
                view.listenTo(view, 'select-category', data => this.navigateToList(data));
            });
        },

        navigateToList(data) {
            this.catalogTreeData = Espo.Utils.cloneDeep(data || {});
            const options = {
                isReturn: true,
                callback: this.expandCatalogTree.bind(this)
            };
            this.getRouter().navigate(`#${this.scope}`);
            this.getRouter().dispatch(this.scope, null, options);
        },

        expandCatalogTree(list) {
            list.sortCollectionWithCatalogTree(this.catalogTreeData);
            list.render();
        },

        data() {
            return _.extend({
                isCatalogTreePanel: this.isCatalogTreePanel
            }, Dep.prototype.data.call(this))
        },

        applyOverviewFilters() {
            // fields filter
            this.fieldsFilter();

            // multi-language fields filter
            this.multiLangFieldsFilter();

            // hide generic fields
            this.genericFieldsFilter();

            // trigger
            this.model.trigger('overview-filters-applied');
        },

        getFilterFieldViews: function () {
            let fields = {};
            $.each(this.getFieldViews(), function (name, fieldView) {
                if (!fieldView.model.getFieldParam(name, 'advancedFilterDisabled')) {
                    fields[name] = fieldView;
                }
            });

            return fields;
        },

        fieldsFilter: function () {
            // get filter param
            let filter = (this.model.advancedEntityView || {}).fieldsFilter;

            $.each(this.getFilterFieldViews(), (name, fieldView) => {
                let actualFields = this.getFieldManager().getActualAttributeList(fieldView.model.getFieldType(name), name);
                let actualFieldValues = actualFields.map(field => fieldView.model.get(field));
                actualFieldValues = actualFieldValues.concat(this.getAlternativeValues(fieldView));

                let hide = !actualFieldValues.every(value => this.checkFieldValue(filter, value, fieldView.isRequired()));
                this.controlFieldVisibility(fieldView, hide);
            });
        },

        multiLangFieldsFilter: function () {
            // get locale
            let locale = (this.model.advancedEntityView || {}).localesFilter;

            $.each(this.getFilterFieldViews(), function (name, fieldView) {
                let multilangLocale = fieldView.model.getFieldParam(name, 'multilangLocale');

                if (multilangLocale !== null) {
                    if (locale !== null && locale !== '' && multilangLocale !== locale) {
                        fieldView.hide();
                    } else if (fieldView.$el.hasClass('hidden')) {
                        fieldView.show();
                    }
                }
            });
        },

        genericFieldsFilter: function () {
            // prepare is show param
            let isShow = (this.model.advancedEntityView || {}).showGenericFields;

            $.each(this.getFilterFieldViews(), (name, fieldView) => {
                let isMultilang = fieldView.model.getFieldParam(name, 'isMultilang') || false;
                const view = this.getFieldView(name);

                if (isMultilang === true && view) {
                    if (isShow && view.$el.hasClass('hidden')) {
                        view.show();
                    } else if (!isShow && !view.$el.hasClass('hidden')) {
                        view.hide();
                    }
                }
            });
        },

        hotKeySave: function (e) {
            e.preventDefault();
            if (this.mode === 'edit') {
                this.actionSave();
            } else {
                let viewsFields = this.getFieldViews();
                Object.keys(viewsFields).forEach(item => {
                    if (viewsFields[item].mode === "edit" ) {
                        viewsFields[item].inlineEditSave();                        
                    }
                });
            }
        },

        afterNotModified(notShow) {
            if (!notShow) {
                let msg = this.translate('notModified', 'messages');
                Espo.Ui.warning(msg, 'warning');
            }
            this.enableButtons();
        },

        getBottomPanels() {
            let bottomView = this.getView('bottom');
            if (bottomView) {
                return bottomView.nestedViews;
            }
            return null;
        },

        setDetailMode() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].setListMode === 'function') {
                        panels[panel].setListMode();
                    }
                }
            }
            Dep.prototype.setDetailMode.call(this);
        },

        setEditMode() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].setEditMode === 'function') {
                        panels[panel].setEditMode();
                    }
                }
            }
            Dep.prototype.setEditMode.call(this);
        },

        cancelEdit() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].cancelEdit === 'function') {
                        panels[panel].cancelEdit();
                    }
                }
            }
            Dep.prototype.cancelEdit.call(this);
        },

        handlePanelsFetch() {
            let changes = false;
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].panelFetch === 'function') {
                        changes = panels[panel].panelFetch() || changes;
                    }
                }
            }
            return changes;
        },

        validatePanels() {
            let notValid = false;
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].validate === 'function') {
                        notValid = panels[panel].validate() || notValid;
                    }
                }
            }
            return notValid
        },

        handlePanelsSave() {
            let panels = this.getBottomPanels();
            if (panels) {
                for (let panel in panels) {
                    if (typeof panels[panel].save === 'function') {
                        panels[panel].save();
                    }
                }
            }
        },

        save(callback, skipExit) {
            (this.notSavedFields || []).forEach(field => {
                const keys = this.getFieldManager().getAttributeList(this.model.getFieldType(field), field);
                keys.forEach(key => delete this.model.attributes[key]);
            });

            this.beforeBeforeSave();

            let data = this.fetch();

            let self = this;
            let model = this.model;

            let initialAttributes = this.attributes;

            let beforeSaveAttributes = this.model.getClonedAttributes();

            data = _.extend(Espo.Utils.cloneDeep(beforeSaveAttributes), data);

            let gridInitPackages = false;
            let packageView = false;
            let bottomView = this.getView('bottom');
            if (bottomView) {
                packageView = bottomView.getView('productTypePackages');
                if (packageView) {
                    gridInitPackages = packageView.getInitAttributes();
                }
            }

            let attrs = false;
            let gridPackages = false;
            if (model.isNew()) {
                attrs = data;
            } else {
                for (let name in data) {
                    if (name !== 'id'&& gridInitPackages && Object.keys(gridInitPackages).indexOf(name) > -1) {
                        if (!_.isEqual(gridInitPackages[name], data[name])) {
                            (gridPackages || (gridPackages = {}))[name] = data[name];
                        }
                        continue;
                    }

                    if (_.isEqual(initialAttributes[name], data[name])) {
                        continue;
                    }
                    (attrs || (attrs = {}))[name] = data[name];
                }
            }

            let beforeSaveGridPackages = false;
            if (gridPackages && packageView) {
                let gridModel = packageView.getView('grid').model;
                beforeSaveGridPackages = gridModel.getClonedAttributes();
                gridModel.set(gridPackages, {silent: true})
            }

            if (attrs) {
                model.set(attrs, {silent: true});
            }

            const panelsChanges = this.handlePanelsFetch();

            const overviewValidation = this.validate();
            const panelValidation = this.validatePanels();

            if (overviewValidation || panelValidation) {
                if (gridPackages && packageView && beforeSaveGridPackages) {
                    packageView.getView('grid').model.attributes = beforeSaveGridPackages;
                }

                model.attributes = beforeSaveAttributes;

                this.trigger('cancel:save');
                this.afterNotValid();
                return;
            }

            if (gridPackages && packageView) {
                packageView.save();
            }

            this.handlePanelsSave();

            if (!attrs) {
                this.afterNotModified(gridPackages || panelsChanges);
                this.trigger('cancel:save');
                return true;
            }

            this.beforeSave();

            this.trigger('before:save');
            model.trigger('before:save');

            model.save(attrs, {
                success: function () {
                    this.afterSave();
                    if (self.isNew) {
                        self.isNew = false;
                    }
                    this.trigger('after:save');
                    model.trigger('after:save');

                    if (!callback) {
                        if (!skipExit) {
                            if (self.isNew) {
                                this.exit('create');
                            } else {
                                this.exit('save');
                            }
                        }
                    } else {
                        callback(this);
                    }
                }.bind(this),
                error: function (e, xhr) {
                    let r = xhr.getAllResponseHeaders();
                    let response = null;

                    if (xhr.status == 409) {
                        let header = xhr.getResponseHeader('X-Status-Reason');
                        try {
                            let response = JSON.parse(header);
                        } catch (e) {
                            console.error('Error while parsing response');
                        }
                    }

                    if (xhr.status == 400) {
                        if (!this.isNew) {
                            this.model.set(this.attributes);
                        }
                    }

                    if (response) {
                        if (response.reason == 'Duplicate') {
                            xhr.errorIsHandled = true;
                            self.showDuplicate(response.data);
                        }
                    }

                    this.afterSaveError();

                    model.attributes = beforeSaveAttributes;
                    self.trigger('cancel:save');

                }.bind(this),
                patch: !model.isNew()
            });
            return true;
        }
    })
);

