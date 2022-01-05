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
                let completeView = panelView.getParentView().getParentView().getView('side').getView('complete');
                if (completeView) {
                    completeView.actionRefresh();
                }

                if (panelView && panelView.model) {
                    panelView.model.trigger('after:attributesSave');
                    panelView.actionRefresh();
                }
            });

            this.listenTo(this, 'change:enumLocaleValue', eventModel => {
                this.updateEnumLocaleValue(eventModel);
            });

            this.runPipeline('actionShowRevisionAttribute');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        updateEnumLocaleValue(eventModel) {
            let position = null;
            if (eventModel.get('attributeType') === 'multiEnum') {
                position = [];
                $.each(eventModel.get('value'), (k, v) => {
                    $.each(eventModel.get('typeValue'), (key, item) => {
                        if (v === item) {
                            position.push(key);
                        }
                    });
                });
            } else {
                $.each(eventModel.get('typeValue'), (key, item) => {
                    if (eventModel.get('value') === item) {
                        position = key;
                    }
                });
            }

            let viewName = eventModel.get('mainLanguageId') || eventModel.get('id');

            if (this.nestedViews[viewName] && this.nestedViews[viewName].getView('valueField') && this.nestedViews[viewName].getView('valueField').model) {
                let ids = Espo.Utils.clone(this.nestedViews[viewName].getView('valueField').model.get('languagesIds')) || [];
                ids.push(viewName);
                ids.forEach(id => {
                    if (eventModel.get('id') !== id) {
                        let relatedModel = this.nestedViews[id].getView('valueField').model;
                        if (eventModel.get('attributeType') === 'multiEnum') {
                            let value = [];
                            $.each(position, (k, v) => {
                                value.push(relatedModel.get('typeValue')[v]);
                            });
                            relatedModel.set('value', value);
                        } else {
                            if (relatedModel.get('typeValue') && relatedModel.get('typeValue')[position]) {
                                relatedModel.set('value', relatedModel.get('typeValue')[position]);
                            }
                        }
                    }
                });
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
                    if (
                        fieldView
                        && fieldView.model
                        && !fieldView.model.getFieldParam(fieldView.name, 'readOnly')
                        && typeof fieldView.setMode === 'function'
                    ) {
                        fieldView.setMode(mode);
                        fieldView.reRender();
                    }
                }
            });
        }

    })
);