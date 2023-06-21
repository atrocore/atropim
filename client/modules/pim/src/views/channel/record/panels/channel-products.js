/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('pim:views/channel/record/panels/channel-products', 'views/record/panels/for-relationship-type', Dep => {

    return Dep.extend({


        setup() {
            Dep.prototype.setup.call(this);
            const selectAction = this.actionList.find(a => a.action === 'selectRelatedEntity')
            if (selectAction) {
                selectAction.data.massRelateDisabled = false
            }
        },

        createRelationshipEntitiesViaIds(selectObj) {
            this.notify('Please wait...');
            let foreignWhere = null
            if (Array.isArray(selectObj)) {
                foreignWhere = [
                    {
                        type: 'equals',
                        attribute: 'id',
                        value: selectObj.map(o => o.id)
                    }
                ]
            } else {
                foreignWhere = selectObj.where
            }

            this.ajaxPostRequest(`${this.model.name}/${this.link}/relation`, {
                where: [
                    {
                        type: "equals",
                        attribute: "id",
                        value: this.model.id
                    }
                ],
                foreignWhere: foreignWhere,
            }).then(() => {
                this.notify('Created', 'success');
                this.actionRefresh();
                this.model.trigger('after:relate', this.panelName);
            });
        },

        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                $.ajax({
                    url: `${this.model.name}/${this.link}/relation`,
                    data: JSON.stringify({
                        where: [
                            {
                                type: "equals",
                                attribute: "id",
                                value: this.model.id
                            }
                        ],
                        foreignWhere: [],
                    }),
                    type: 'DELETE',
                    contentType: 'application/json',
                }).done(response => {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:unrelate');
                });
            });
        },

    });
});

