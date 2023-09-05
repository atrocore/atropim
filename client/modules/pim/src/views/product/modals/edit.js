/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/modals/edit', 'views/modals/edit',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            let config = this.getConfig(),
                assignedUser = config.get('assignedUserProductOwnership') || 'notInherit',
                ownerUser = config.get('ownerUserProductOwnership') || 'notInherit',
                teams = config.get('teamsProductOwnership') || 'notInherit';

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
        },

        setupOwnership: function (param, field) {
            switch (param) {
                case 'fromCatalog':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('Catalog', 'catalogId', field);
                    break;
                case 'fromClassification':
                    this.clearModel(field);
                    this.setRelatedOwnershipInfo('Classification', 'classificationId', field);
                    break;
                case 'notInherit':
                    this.clearModel(field);
                    break;
            }
        },

        clearModel: function (field) {
            let isLinkMultiple = (this.getMetadata().get(['entityDefs', 'Product', 'fields', field, 'type']) === 'linkMultiple'),
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

        ownershipRequestAndSetup(scope, target, field) {
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
                    this.clearModel(field);
                }
            }
        }
    })
);
