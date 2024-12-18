/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/associated-main-products',
    ['views/record/panels/relationship', 'views/record/panels/bottom', 'pim:views/record/panels/records-in-groups'],
    (Dep, BottomPanel, RecordInGroup) => Dep.extend({
        groupScope: 'Association',

        disableSelect: true,

        template: 'pim:product/record/panels/associated-main-products',

        rowActionsView: 'views/record/row-actions/relationship-no-remove',

        groups: [],

        setup() {
            let bottomPanel = new BottomPanel();
            bottomPanel.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

            if (!this.scope && !(this.link in this.model.defs.links)) {
                throw new Error('Link \'' + this.link + '\' is not defined in model \'' + this.model.name + '\'');
            }
            this.title = this.title || this.translate(this.link, 'links', this.model.name);
            this.scope = this.scope || this.model.defs.links[this.link].entity;
            this.layoutName = 'listSmall';
            if (this.checkAclAction('create')) {
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

            if (!this.disableDeleteAll && this.checkAclAction('delete')) {
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

            this.listenTo(this.model, 'after:unrelate', () => {
                this.actionRefresh();
            });

            this.listenTo(this.model, 'after:relate', (link) => {
                if(link === 'associatedMainProducts') {
                    this.actionRefresh();
                }
            });

            this.setupListLayout();
            this.wait(true);

            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;
                this.fetchCollectionGroups(() => this.wait(false));
            });

            this.listenTo(this, 'after-groupPanels-rendered', () => {
                setTimeout(() => {
                    this.regulateTableSizes()
                },500)
            });
        },

        data() {
            return _.extend({
                groups: this.groups,
                groupScope: this.groupScope
            }, Dep.prototype.data.call(this));
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.buildGroups();
        },

        actionRefresh() {
            this.fetchCollectionGroups(() => {
                this.reRender();
            })
        },

        buildGroups() {
            if (!this.groups || this.groups.length < 1) {
                return;
            }

            let areRendered = [];

            this.groups.forEach((group, key) => {
                this.getHelper().layoutManager.get('Product', this.layoutName, layout => {
                    let list = [];
                    layout.forEach(item => {
                        if (item.name) {
                            let field = item.name;
                            let fieldType = this.getMetadata().get(['entityDefs', 'Product', 'fields', field, 'type']);
                            if (fieldType) {
                                this.getFieldManager().getAttributeList(fieldType, field).forEach(attribute => {
                                    list.push(attribute);
                                });
                            }
                        }
                    });

                    this.getCollectionFactory().create('Product', groupCollection => {
                        this.initGroupCollection(group, groupCollection, () => {
                            groupCollection.data.select = list.join(',')
                            groupCollection.fetch().then(() => {
                                let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';
                                let options = {
                                    scope: 'Product',
                                    collection: groupCollection,
                                    layoutName: this.layoutName,
                                    listLayout: this.prepareListLayout(layout),
                                    rowActionsView: 'views/record/row-actions/relationship-no-unlink',
                                    checkboxes: false,
                                    buttonsDisabled: true,
                                    showMore: false,
                                    el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                                };

                                this.createView('associatedProduct' + group.key, viewName, options, view => {
                                    view.render();
                                    if(view.isRendered()) {
                                        areRendered.push(group.key);
                                        if(areRendered.length === this.groups.length) {
                                            this.trigger('after-groupPanels-rendered');
                                        }
                                    }
                                    view.once('after:render', () => {
                                        areRendered.push(group.key);
                                        if(areRendered.length === this.groups.length) {
                                            this.trigger('after-groupPanels-rendered');
                                        }
                                    })
                                });
                            });
                        });
                    });
                });
            });
        },

        initGroupCollection(group, groupCollection, callback) {
            groupCollection.url = 'Product';
            groupCollection.collectionOnly = true;
            groupCollection.maxSize = 999
            groupCollection.where = [
                {
                    type: 'linkedWith',
                    attribute: 'associatedRelatedProduct',
                    subQuery: [
                        {
                            type: 'equals',
                            attribute: 'associationId',
                            value: group.id
                        },
                        {
                            type: 'equals',
                            attribute: 'mainProductId',
                            value: this.model.id
                        }
                    ]
                }
            ]
            callback();
        },

        fetchCollectionGroups(callback) {
            const data = {
                where: [
                    {
                        type: 'bool',
                        value: ['usedAssociations'],
                        data: {
                            usedAssociations: {
                                mainProductId: this.model.id
                            }
                        }
                    }
                ]
            }
            this.ajaxGetRequest('Association', data).then(data => {
                this.groups = data.list.map(row => ({id: row.id, key: row.id, label: row.name}));
                callback();
            });
        },

        prepareListLayout(layout) {
            layout.forEach((v, k) => {
                layout[k]['notSortable'] = true;
            });

            return layout;
        },

        deleteEntities(groupId) {
            const data = {
                where: [
                    {
                        type: "equals",
                        attribute: "id",
                        value: this.model.id
                    }
                ],
                foreignWhere: [],
            }
            if (groupId) {
                data.associationId = groupId
            }
            $.ajax({
                url: `${this.model.name}/${this.link}/relation`,
                data: JSON.stringify(data),
                type: 'DELETE',
                contentType: 'application/json',
                success: function () {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.model.trigger('after:unrelate');
                }.bind(this),
                error: function () {
                    this.notify('Error occurred', 'error');
                }.bind(this),
            })
        },
        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                this.deleteEntities()
            });
        },

        actionUnlinkGroup(data) {
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

        checkAclAction(action) {
            return this.getAcl().check(this.scope, action);
        },

        regulateTableSizes() {
            RecordInGroup.prototype.regulateTableSizes.call(this);
        }
    })
);