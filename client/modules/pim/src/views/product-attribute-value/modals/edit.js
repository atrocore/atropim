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

Espo.define('pim:views/product-attribute-value/modals/edit', 'treo-core:views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            let config = this.getConfig(),
                assignedUser = config.get('assignedUserAttributeOwnership'),
                ownerUser = config.get('ownerUserAttributeOwnership'),
                teams = config.get('teamsAttributeOwnership');

            if (!assignedUser && !ownerUser && !teams) {
                if (!this.model.id) {
                    let modelProduct = this.options.relate.model;
                    this.model.set({
                        assignedUserId: modelProduct.get('assignedUserId'),
                        assignedUserName: modelProduct.get('assignedUserName'),
                        ownerUserId: modelProduct.get('ownerUserId'),
                        ownerUserName: modelProduct.get('ownerUserName'),
                        teamsIds: modelProduct.get('teamsIds'),
                        teamsNames: modelProduct.get('teamsNames'),
                    });
                }
            } else {
                if (this.getAcl().get('assignmentPermission') !== 'no'
                    && this.getAcl().checkScope('User')
                    && this.getEntityReadScopeLevel('User') !== 'no') {
                    this.setupOwnership(assignedUser, 'assignedUser');

                    this.setupOwnership(ownerUser, 'ownerUser');
                }

                if (this.getAcl().checkScope('Team')
                    && this.getEntityReadScopeLevel('Team') !== 'no') {
                    this.setupOwnership(teams, 'teams');
                }

                this.reRender();
            }
        },

        setupOwnership: function (param, field) {
            switch (param) {
                case 'fromAttribute':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('Attribute', 'attributeId', field);
                    break;
                case 'fromProduct':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('Product', 'productId', field);
                    break;
                case 'notInherit':
                    this.clearModel(field);
                    break;
            }
        },

        clearModel: function (field) {
            let isLinkMultiple = (this.getMetadata().get(['entityDefs', 'ProductAttributeValue', 'fields', field, 'type']) === 'linkMultiple'),
                idField = field + (isLinkMultiple ? 'Ids' : 'Id'),
                nameField = field + (isLinkMultiple ? 'Names' : 'Name');

            this.model.set({
                [idField]: null,
                [nameField]: null
            });
        },

        getEntityReadScopeLevel: function (entity) {
            return this.getAcl().data.table[entity].read;
        },

        throwError403: function () {
            let msg = this.getLanguage().translate('Error') + ' 403: ';
            msg += this.getLanguage().translate('Access denied');
            Espo.Ui.error(msg);
        },

        setRelatedOwnershipInfo: function (scope, target, field) {
            this.listenTo(this.model, `change:${target} change:isInherit` + Espo.Utils.upperCaseFirst(field), () => {
                this.ownershipRequestAndSetup(scope, target, field);
            });
        },

        ownershipRequestAndSetup: function (scope, target, field) {
            if (this.model.get('isInherit' + Espo.Utils.upperCaseFirst(field))) {
                let id = this.model.get(target),
                    isLinkMultiple = (this.getMetadata().get(['entityDefs', scope, 'fields', field, 'type']) === 'linkMultiple'),
                    foreign = this.getMetadata().get(['entityDefs', scope, 'links', field, 'entity']),
                    idField = field + (isLinkMultiple ? 'Ids' : 'Id'),
                    nameField = field + (isLinkMultiple ? 'Names' : 'Name');

                if (id && foreign) {
                    this.ajaxGetRequest(`${scope}/${id}`)
                        .then(response => {
                            let data = {
                                [idField]: response[idField],
                                [nameField]: response[nameField]
                            };

                            switch (this.getEntityReadScopeLevel(foreign)) {
                                case 'team':
                                    if (foreign === 'User') {
                                        this.ajaxGetRequest(`${foreign}/${response[idField]}`).then(res => {
                                            this.model.set(data);
                                        });
                                    }

                                    if (foreign === 'Team') {
                                        if (!response[idField].filter(item => !this.getUser().getTeamIdList().includes(item)).length) {
                                            this.model.set(data);
                                        } else {
                                            this.throwError403();
                                        }
                                    }
                                    break;
                                case 'own':
                                    if (foreign === 'User'
                                        && response[idField] === this.getUser().get('id')) {
                                        this.model.set(data);
                                    } else {
                                        this.throwError403();
                                    }
                                    break;
                                default:
                                    this.model.set(data);
                            }
                        });
                } else {
                    this.model.set({
                        [idField]: null,
                        [nameField]: null
                    });
                }
            }
        }
    })
);
