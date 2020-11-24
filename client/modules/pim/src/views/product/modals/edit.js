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

Espo.define('pim:views/product/modals/edit', 'treo-core:views/modals/edit',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            let config = this.getConfig(),
                assignedUser = config.get('assignedUserProductOwnership'),
                ownerUser = config.get('ownerUserProductOwnership'),
                teams = config.get('teamsProductOwnership');

            this.setupOwnership(assignedUser, 'assignedUser');

            this.setupOwnership(ownerUser, 'ownerUser');

            this.setupOwnership(teams, 'teams');

            this.reRender();
        },

        setupOwnership: function (param, field) {
            switch (param) {
                case 'fromCatalog':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('Catalog', 'catalogId', field);
                    break;
                case 'fromProductFamily':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('ProductFamily', 'productFamilyId', field);
                    break;
                case 'notInherit':
                    this.clearModel(field);
                    break;
                default:
                    if (field === 'teams') {
                        this.model.set({
                            teamsIds: this.getUser().get('teamsIds'),
                            teamsNames: this.getUser().get('teamsNames')
                        });
                    }
            }
        },

        clearModel: function (field) {
            this.model.set({
                [field + 'Id']: null,
                [field + 'Name']: null
            });
        },

        setRelatedOwnershipInfo: function (scope, target, field) {
            this.listenTo(this.model, `change:${target}`, () => {
                let id = this.model.get(target),
                    isLinkMultiple = (this.getMetadata().get(['entityDefs', scope, 'fields', field, 'type']) === 'linkMultiple'),
                    idField = field + (isLinkMultiple ? 'Ids' : 'Id'),
                    nameField = field + (isLinkMultiple ? 'Names' : 'Name');

                if (id) {
                    this.ajaxGetRequest(`${scope}/${id}`)
                        .then(response => {
                            this.model.set({
                                [idField]: response[idField],
                                [nameField]: response[nameField]
                            });
                        });
                } else {
                    this.model.set({
                        [idField]: null,
                        [nameField]: null
                    });
                }
            });
        }
    })
);