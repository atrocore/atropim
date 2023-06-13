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

Espo.define('pim:views/product/record/panels/associated-main-products', ['views/record/panels/for-relationship-type', 'views/record/panels/bottom', 'search-manager'],
    (Dep, BottomPanel, SearchManager) => Dep.extend({
        groups: [],
        setup() {
            Dep.prototype.setup.call(this);

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
            this.fetchAssociations()
        },
        fetchAssociations(callback) {
            this.ajaxGetRequest('AssociatedProduct', {
                select: 'associationId,associationName',
                tabId: this.defs.tabId,
                where: [
                    {attribute: 'mainProductId', type: 'equals', value: this.model.get('id')}
                ],
            }).then(data => {
                this.groups = data.list.filter((e, idx, array) => {
                    return array.findIndex(el => el.associationId === e.associationId) === idx;
                }).map(e => ({
                    associationId: e.associationId,
                    associationName: e.associationName
                }));
                console.log('groups', this.groups)
                callback();
            });
        },
    })
);