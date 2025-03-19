/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/list-in-groups', 'views/record/list',
    Dep => Dep.extend({

        hiddenInEditColumns: ['isRequired'],

        template: 'pim:record/list-in-groups',

        events: _.extend({
            'click [data-action="unlinkGroup"]': function (e) {
                e.preventDefault();
                this.trigger('remove-group', $(e.currentTarget).data())
            },
            'click [data-action="unlinkGroupHierarchy"]': function (e) {
                e.preventDefault();
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

        getStatusIcons: function (model) {
            const htmlIcons = Dep.prototype.getStatusIcons.call(this, model) || [];

            if (model.get('isRequired')) {
                htmlIcons.push(`<span class="fas fa-sm fa-asterisk required-sign pressable-icon" title="${this.translate('Required')}"></span>`)
            }

            if (model.urlRoot === 'ProductAttributeValue') {
                const isPavValueInherited = model.get('isPavValueInherited');

                if (model.get('isVariantSpecificAttribute')) {
                    htmlIcons.push(`<span class="fas fa-star fa-sm" title="${this.translate('isVariantSpecificAttribute', 'fields', 'ProductAttributeValue')}"></span>`);
                }

                if (isPavValueInherited === true) {
                    htmlIcons.push(`<span title="${this.translate('inherited')}" class="fas fa-link fa-sm"></span>`);
                } else if (isPavValueInherited === false) {
                    htmlIcons.push(`<span title="${this.translate('notInherited')}" class="fas fa-unlink fa-sm"></span>`);
                }

                if (model.get('isPavRelationInherited')) {
                    htmlIcons.push(`<span class="fa fa-sitemap fa-sm" title="${this.translate('isPavRelationInherited', 'fields', 'ProductAttributeValue')}"></span>`);
                }
            }

            return htmlIcons;
        },

        afterRenderStatusIcons(icons, model) {
            if (model.name === 'ProductAttributeValue') {
                icons.find('span.required-sign').click(() => {
                    this.getParentModel().trigger('toggle-required-fields-highlight');
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        actionSetPavAsInherited(data) {
            let model = null;
            if (this.collection) {
                model = this.collection.get(data.id);
            }

            this.ajaxPostRequest(`ProductAttributeValue/action/inheritPav`, {id: data.id}).then(response => {
                this.notify('Saved', 'success');
                model?.trigger('after:attributesSave');
                this.$el.parents('.panel').find('.action[data-action=refresh]').click();
            });
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
                    this.getEditableFields().forEach(field => {
                        let fieldView = rowView.getView(field + 'Field');
                        if (
                            fieldView
                            && fieldView.model
                            && !fieldView.model.getFieldParam(fieldView.name, 'readOnly')
                            && typeof fieldView.setMode === 'function'
                        ) {
                            fieldView.setMode(mode);
                            fieldView.reRender();
                        }
                    })
                }
            });
        },

        getEditableFields(){
            let fields = (this.listLayout ?? []).filter(p => p.editable).map(p => p.name);
            if(!fields.includes('value')){
                fields.push('value')
            }
            return fields;
        },
    })
);
