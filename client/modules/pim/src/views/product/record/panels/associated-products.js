/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/associated-products',
    ['pim:views/record/panels/records-in-groups', 'views/record/panels/bottom', 'views/record/panels/relationship'],
    (Dep, BottomPanel, Relationship) => Dep.extend({

        scope: 'Product',
        groupScope: 'Association',
        disableSelect: true,

        template: 'pim:product/record/panels/associated-main-products',

        rowActionsView: 'views/record/row-actions/relationship-no-remove',

        groups: [],

        disableCollectionFetch: true,

        getCreateLink() {
            return 'associatedMainProducts';
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.defs.recordListView = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list')

            this.listenTo(this, 'groups-rendered', () => {
                setTimeout(() => this.regulateTableSizes(), 500)
            });
        },

        getModel(data, evt) {
            const idx = $(evt.target).closest('.group').index()
            const key = this.groups[idx].key
            return this.getView(key).collection.get(data.cid)
        },

        afterGroupRender() {
            this.groups.forEach(group => {
                const groupCollection = this.getView(group.key).collection;
                groupCollection.forEach(item => {
                    item = item.relationModel
                    if (this.collection.get(item.get('id'))) {
                        this.collection.remove(item.get('id'));
                    }
                    this.collection.add(item);
                });
            })

            this.collection.total = this.collection.length
            this.collection.trigger('update-total', this.collection)
            this.trigger('after-groupPanels-rendered')
        },

        initGroupCollection(group, groupCollection, callback) {
            this.getHelper().layoutManager.get('Product', this.layoutName, 'Product.associatedProducts', null, data => {
                groupCollection.url = this.model.name + '/' + this.model.id + '/' + this.link;
                groupCollection.collectionOnly = true;
                groupCollection.maxSize = 999
                groupCollection.data.whereRelation = [
                    {
                        type: 'equals',
                        attribute: 'associationId',
                        value: group.id
                    }
                ]
                let list = [];
                data.layout.forEach(item => {
                    if (item.name) {
                        let field = item.name;
                        let fieldType = this.getMetadata().get(['entityDefs', 'Product', 'fields', field, 'type']);
                        if (fieldType) {
                            this.getFieldManager().getAttributeList(fieldType, field).forEach(attribute => {
                                if (fieldType === 'link' || fieldType === 'linkMultiple') {
                                    const foreignEntity = this.getMetadata().get(['entityDefs', 'Product', 'links', field, 'entity']);
                                    let foreignName = this.getMetadata().get(['entityDefs', 'Product', 'fields', field, 'foreignName']);
                                    if (foreignEntity && this.getMetadata().get(['entityDefs', foreignEntity, 'fields', 'name'])) {
                                        foreignName = 'name';
                                    }

                                    if (!foreignName && (attribute.endsWith('Name') || attribute.endsWith('Names'))) {
                                        return;
                                    }
                                }

                                list.push(attribute);
                            });
                        }
                    }
                });
                groupCollection.data.select = list.join(',')

                groupCollection.fetch().success(() => {
                    callback();
                });
            })
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

        deleteEntities(groupId) {
            const data = {mainProductId: this.model.id}
            if (groupId) data.associationId = groupId
            this.ajaxPostRequest(`AssociatedProduct/action/RemoveFromProduct`, data)
                .done(response => {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.model.trigger('after:unrelate');
                });
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
        }
    })
);