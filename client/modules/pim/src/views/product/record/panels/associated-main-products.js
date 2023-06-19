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

Espo.define('pim:views/product/record/panels/associated-main-products', ['views/record/panels/relationship', 'views/record/panels/bottom'],
    (Dep, BottomPanel) => Dep.extend({
        groups: [],
        groupScope: 'Associate',
        rowActionsView: 'views/record/row-actions/relationship-no-unlink',
        template: 'pim:product/record/panels/associated-main-products',
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

            let create = this.buttonList.find(item => item.action === (this.defs.createAction || 'createRelated'));

            if (this.getAcl().check('AssociatedProducts', 'create') && !create) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.actionCreate || 'createRelated',
                    link: this.link,
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<span class="fas fa-plus"></span>',
                    data: {
                        link: this.link,
                    }
                });
            }
            this.actionList.push({
                label: 'deleteAll',
                action: 'deleteAllRelationshipEntities',
                data: {
                    "relationshipScope": this.scope
                },
                acl: 'delete',
                aclScope: this.scope
            });
            this.wait(true);
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;

                this.setFilter(this.filter);

                this.listenTo(this.model, 'updateAssociations change:classificationId update-all after:relate after:unrelate', link => {
                    if (!link || link === 'associatedMainProducts') {
                        this.getCollectionFactory().create(this.scope, collection => {
                            this.collection = collection;
                            this.actionRefresh();
                        });
                    }
                });

                this.fetchCollectionGroups(() => {
                    this.wait(false);
                });
            });
        },
        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.buildGroups();
        },
        buildGroups() {
            if (!this.groups || this.groups.length < 1) {
                return;
            }

            let count = 0;
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, groupCollection => {
                    groupCollection.url = 'AssociatedProduct';
                    groupCollection.data.select = 'association_id';
                    groupCollection.data.tabId = this.defs.tabId;
                    groupCollection.where = [
                        {
                            type: 'equals',
                            attribute: 'mainProductId',
                            value: this.model.id
                        },
                        {
                            type: 'equals',
                            attribute: 'associationId',
                            value: group.key
                        }
                    ];
                    groupCollection.maxSize = 9999;
                    groupCollection.fetch().success(() => {
                        groupCollection.forEach(item => {
                            if (this.collection.get(item.get('id'))) {
                                this.collection.remove(item.get('id'));
                            }
                            this.collection.add(item);
                        });

                        let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

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
                            groupName: group.label
                        };

                        this.createView(group.key, viewName, options, view => {
                            // view.listenTo(view, 'after:render', () => this.applyOverviewFilters());
                            view.listenTo(view, 'remove-association', (data) => this.unlinkAssociation(data));

                            view.render(() => {
                                count++;
                                if (count === this.groups.length) {
                                    this.trigger('groups-rendered');
                                }
                            });
                        });
                    });
                });
            });
        },
        fetchCollectionGroups(callback) {
            this.ajaxGetRequest('AssociatedProduct/action/GroupsAssociations', {
                productId: this.model.get('id')
            }).then(data => {
                this.groups = data;
                callback();
            });
        },
        actionRefresh() {
            this.fetchCollectionGroups(() => {
                this.reRender();
            });
        },
        deleteEntities(groupId) {
            const data = {productId: this.model.id}
            if (groupId) data.associationId = groupId
            this.ajaxPostRequest(`${this.scope}/action/RemoveFromProduct/${item.id}`,)
                .done(response => {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:unrelate');
                });
        },
        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                this.deleteEntities()
            });
        },

        unlinkAssociation(data) {
            let id = data.id;
            if (!id) {
                return;
            }

            let group = this.groups.find(group => group.id === id);
            if (!group) {
                return;
            }

            this.confirm({
                message: this.translate('removeRelatedProducts', 'messages', 'AssociatedProduct'),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('removing');
                this.deleteEntities(group.id)
            }, this);
        },
    })
);