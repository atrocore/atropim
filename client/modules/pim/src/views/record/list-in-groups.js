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

Espo.define('pim:views/record/list-in-groups', 'views/record/list',
    Dep => Dep.extend({

        hiddenInEditColumns: ['isRequired'],

        template: 'pim:record/list-in-groups',

        events: _.extend({
            'click [data-action="unlinkGroup"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.trigger('remove-group', $(e.currentTarget).data())
            },
            'click [data-action="unlinkGroupHierarchy"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.trigger('remove-group-hierarchically', $(e.currentTarget).data());
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);
            this.groupScope = this.groupScope || this.options.groupScope;
            this.scope = this.scope || this.options.scope;
            this.pipelines = {
                actionShowRevisionAttribute: ['clientDefs', this.scope, 'actionShowRevisionAttribute']
            }

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

            this.runPipeline('actionShowRevisionAttribute');
        },

        afterSave: function () {
            // do nothing
        },

        data() {
            let result = Dep.prototype.data.call(this);

            result.groupScope = this.groupScope;
            result.groupId = this.options.groupId;
            result.headerDefs = this.updateHeaderDefs(result.headerDefs);
            result.rowActionsColumnWidth = this.rowActionsColumnWidth;
            result.editable = !!this.options.groupId && this.getAcl().check(this.scope, 'delete');
            result.hierarchyEnabled = this.options.hierarchyEnabled
            return result;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        updateHeaderDefs(defs) {
            if (defs[0]) {
                defs[0].name = this.options.groupName;

                if (this.options.groupId) {
                    defs[0].id = this.options.groupId;
                }
            }

            if (this.rowActionsView && !this.rowActionsDisabled && this.options.groupId) {
                defs.pop();
            }

            return defs;
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
