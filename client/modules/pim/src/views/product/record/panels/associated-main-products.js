/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/associated-main-products', ['pim:views/record/panels/records-in-groups'],
    (Dep) => Dep.extend({
        groupScope: 'Association',
        disableSelect: true,
        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        initGroupCollection(group, groupCollection, callback) {
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
                callback();
            });
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

        unlinkGroup(data) {
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