/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/records-in-groups', ['views/record/panels/relationship', 'views/record/panels/bottom'],
    (Dep, BottomPanel) => Dep.extend({

        template: 'pim:record/panels/records-in-groups',

        data() {
            return _.extend({
                groups: this.groups,
                groupScope: this.groupScope
            }, Dep.prototype.data.call(this));
        },

        setup() {
            let bottomPanel = new BottomPanel();
            bottomPanel.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

            if (!this.scope && !(this.link in this.model.defs.links)) {
                throw new Error('Link \'' + this.link + '\' is not defined in model \'' + this.model.name + '\'');
            }
            this.title = this.title || this.translate(this.link, 'links', this.model.name);
            this.scope = this.scope || this.model.defs.links[this.link].entity;

            if (!this.getConfig().get('scopeColorsDisabled')) {
                var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
                if (iconHtml) {
                    if (this.defs.label) {
                        this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', this.scope);
                    } else {
                        this.titleHtml = iconHtml + this.title;
                    }
                }
            }

            this.filterList = this.defs.filterList || this.filterList || null;

            if (this.filterList && this.filterList.length) {
                this.filter = this.getStoredFilter();
            }

            if (this.getAcl().check(this.scope, 'create')) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.createAction || 'createRelated',
                    link: this.link,
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<span class="fas fa-plus"></span>',
                    data: {
                        link: this.link,
                        tabId: this.defs.tabId
                    }
                });
            }

            if (!this.disableSelect && this.getAcl().check(this.scope, 'create')) {
                var data = {link: this.defs.name};
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }
                data.boolFilterListCallback = 'getSelectBoolFilterList';
                data.boolFilterDataCallback = 'getSelectBoolFilterData';
                data.afterSelectCallback = this.afterSelectCallback;
                data.scope = this.selectScope;

                this.actionList.unshift({
                    label: 'Select',
                    action: this.defs.selectAction || 'selectRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });

                if (this.getAcl().check(this.groupScope, 'read')) {
                    this.actionList.push({
                        label: this.selectLabel || 'selectGroup',
                        action: 'selectGroup'
                    });
                }
            }

            if (!this.disableDeleteAll && this.getAcl().check(this.scope, 'delete')) {
                this.actionList.push({
                    label: 'deleteAll',
                    action: 'deleteAllRelationshipEntities',
                    data: {
                        "relationshipScope": this.scope
                    },
                    acl: 'delete',
                    aclScope: this.scope
                });
            }

            this.setupActions();

            var layoutName = 'listSmall';
            this.setupListLayout();

            if (this.listLayoutName) {
                layoutName = this.listLayoutName;
            }

            var listLayout = null;
            var layout = this.defs.layout || null;
            if (layout) {
                if (typeof layout == 'string') {
                    layoutName = layout;
                } else {
                    layoutName = 'listRelationshipCustom';
                    listLayout = layout;
                }
            }

            this.layoutName = layoutName;
            this.listLayout = listLayout;

            var sortBy = this.defs.sortBy || null;
            var asc = this.defs.asc || null;

            if (this.defs.orderBy) {
                sortBy = this.defs.orderBy;
                asc = true;
                if (this.defs.orderDirection) {
                    if (this.defs.orderDirection && (this.defs.orderDirection === true || this.defs.orderDirection.toLowerCase() === 'DESC')) {
                        asc = false;
                    }
                }
            }

            this.wait(true);
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;

                this.setFilter(this.filter);

                this.listenTo(this.model, 'change:classificationId update-all after:relate after:unrelate', link => {
                    if (!link || link === this.link) {
                        this.getCollectionFactory().create(this.scope, collection => {
                            this.collection = collection;
                            this.actionRefresh();
                        });
                    }
                });

                this.listenTo(this.model, 'overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });

                this.fetchCollectionGroups(() => {
                    this.wait(false);
                });
            });
        },
        afterRender() {
            Dep.prototype.afterRender.call(this);
            Dep.prototype.setupTotal.call(this)
            console.log(this.collection);
            this.buildGroups();

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        fetchCollectionGroups(callback) {
            this.groups = []
            callback()
        },

        getSelectFields() {
            return this.baseSelectFields || [];
        },

        initGroupCollection(group, groupCollection) {
        },

        buildGroups() {
            if (!this.groups || this.groups.length < 1) {
                return;
            }
            let count = 0;
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, groupCollection => {
                    this.initGroupCollection(group, groupCollection)
                    groupCollection.fetch().success(() => {
                        groupCollection.forEach(item => {
                            if (this.collection.get(item.get('id'))) {
                                this.collection.remove(item.get('id'));
                            }
                            this.collection.add(item);
                        })

                        let viewName = this.defs.recordListView || 'pim:views/record/list-in-groups';

                        let options = {
                            collection: groupCollection,
                            layoutName: this.layoutName,
                            listLayout: this.listLayout,
                            checkboxes: false,
                            rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                            buttonsDisabled: true,
                            el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                            showMore: false,
                            groupId: group.id,
                            groupName: group.label,
                            groupScope: this.groupScope,
                            hierarchyEnabled: this.hierarchyEnabled,
                        };

                        this.createView(group.key, viewName, options, view => {
                            view.listenTo(view, 'after:render', () => this.applyOverviewFilters());
                            view.listenTo(view, 'remove-group', (data) => this.unlinkGroup(data));
                            view.listenTo(view, 'remove-group-hierarchically', (data) => this.unlinkGroupHierarchy(data));

                            view.render(() => {
                                count++;
                                if (count === this.groups.length) {
                                    this.afterGroupRender()
                                    this.applyOverviewFilters();
                                    this.trigger('groups-rendered');
                                }
                            });
                        });
                    });
                });
            });
        },

        afterGroupRender() {
        },

        applyOverviewFilters() {
            const fieldFilter = this.getStorage().get('fieldFilter', 'OverviewFilter') || ['allValues'];
            const languageFilter = this.getStorage().get('languageFilter', 'OverviewFilter') || ['allLanguages'];
            const scopeFilter = this.getStorage().get('scopeFilter', 'OverviewFilter') || ['allChannels'];

            $.each(this.getValueFields(), (name, fieldView) => {
                let value = fieldView.model.get('value'),
                    hide = false;

                if (!fieldFilter.includes('allValues')) {
                    // hide filled
                    if (!hide && fieldFilter.includes('filled')) {
                        hide = this.isEmptyValue(value);
                    }

                    // hide empty
                    if (!hide && fieldFilter.includes('empty')) {
                        hide = !this.isEmptyValue(value);
                    }

                    // hide optional
                    if (!hide && fieldFilter.includes('optional')) {
                        hide = this.isRequiredValue(fieldView);
                    }

                    // hide required
                    if (!hide && fieldFilter.includes('required')) {
                        hide = !this.isRequiredValue(fieldView);
                    }
                }

                if (!scopeFilter.includes('allChannels')) {
                    // hide channel
                    if (!hide && !this.isScopeValid(fieldView, scopeFilter)) {
                        hide = true;
                    }
                }

                // for languages
                if (!languageFilter.includes('allLanguages')) {
                    if (!hide && this.getConfig().get('isMultilangActive') && (this.getConfig().get('inputLanguageList') || []).length) {
                        let language = fieldView.model.get('language') || 'main';
                        if (!languageFilter.includes(language)) {
                            hide = true;
                        }
                    }
                }

                this.controlRowVisibility(fieldView, name, hide);
            });
        },

        isEmptyValue(value) {
            return value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        isRequiredValue(view) {
            return view.model.get('isRequired') || false
        },


        getValueFields() {
            let fields = {};
            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView) {
                    groupView.rowList.forEach(row => {
                        let rowView = groupView.getView(row);
                        if (rowView) {
                            let containerView = rowView.getView('valueField');
                            if (containerView) {
                                let fieldView = containerView.getView('valueField');
                                if (fieldView) {
                                    fieldView.groupKey = group.key;
                                    fields[row] = fieldView;
                                }
                            }
                        }
                    });
                }
            });
            return fields;
        },

        controlRowVisibility(fieldView, rowId, hide) {
            let groupView = this.getView(fieldView.groupKey);
            let rowView = groupView.getView(rowId);
            if (hide) {
                rowView.$el.addClass('hidden');
            } else {
                rowView.$el.removeClass('hidden');
            }
            this.controlGroupVisibility(groupView);
        },

        controlGroupVisibility(groupView) {
            if (groupView.$el.find('.list-row.hidden').size() === (groupView.rowList || []).length) {
                groupView.$el.parent().addClass('hidden');
            } else {
                groupView.$el.parent().removeClass('hidden');
            }
        },

        getSelectBoolFilterData(boolFilterList) {
            let data = {};
            if (Array.isArray(boolFilterList)) {
                boolFilterList.forEach(item => {
                    if (this.boolFilterData && typeof this.boolFilterData[item] === 'function') {
                        data[item] = this.boolFilterData[item].call(this);
                    }
                });
            }
            return data;
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

        actionRefresh() {
            this.fetchCollectionGroups(() => {
                this.reRender();
            });
        },
        setListMode() {
            this.mode = 'list';

            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView) {
                    groupView.setListMode();
                }
            });

            this.reRender();
        },

        unlinkGroup(data) {

        },

        actionDeleteAllRelationshipEntities(data) {

        },


    })
);
