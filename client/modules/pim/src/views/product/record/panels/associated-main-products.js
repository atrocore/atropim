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

Espo.define('pim:views/product/record/panels/associated-main-products', ['pim:views/record/panels/records-in-groups'],
    (Dep) => Dep.extend({
        groups: [],
        selectScope: 'Associate',
        groupScope: 'Association',
        disableSelect: true,
        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        initGroupCollection(group, groupCollection) {
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
            const data = {productId: this.model.id}
            if (groupId) data.associationId = groupId
            this.ajaxPostRequest(`${this.scope}/action/RemoveFromProduct`, data)
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

        unlinkEntity(data) {
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