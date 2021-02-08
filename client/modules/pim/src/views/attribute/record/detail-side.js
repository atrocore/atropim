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

Espo.define('pim:views/attribute/record/detail-side', 'views/record/detail-side',
    Dep => Dep.extend({

        multiLangOwnershipPanel: {
            name: 'default',
            label: '',
            view: 'pim:views/attribute/record/panels/default-side',
            isForm: true,
            options: {
                fieldList: [
                    {
                        name: 'ownerUser'
                    },
                    {
                        name: 'assignedUser'
                    },
                    {
                        name: 'teams'
                    }
                ]
            }
        },

        setup() {
            this.createMultilangOwnershipPanel();
            this.listenTo(this.model, 'after:save', function () {
                this.panelList.forEach(function (panel) {
                    if (this.model.get('isMultilang')) {
                        this.showPanel(panel.name);
                    } else if (panel.name !== 'default') {
                        this.hidePanel(panel.name);
                    }
                }, this)
            }, this);

            Dep.prototype.setup.call(this);
        },

        createMultilangOwnershipPanel() {
            let config = this.getConfig(),
                inputLanguageList = config.get('inputLanguageList') || [];

            if (config.get('assignedUserAttributeOwnership') && config.get('ownerUserAttributeOwnership')
                && config.get('teamsAttributeOwnership')) {
                if (this.model.get('isMultilang') && config.get('isMultilangActive') && inputLanguageList.length) {
                    inputLanguageList.forEach(lang => {
                        let panel = Espo.Utils.cloneDeep(this.multiLangOwnershipPanel);

                        panel.options.fieldList.forEach((item, index) => {
                            let name = lang.split('_').reduce((prev, curr) => prev + Espo.Utils.upperCaseFirst(curr.toLocaleLowerCase()), item.name);

                            if (item.name === 'teams') {
                                this.model.defs.fields[name] = this.model.defs.fields[item.name];
                            }
                            this.model.defs.links[name] = this.model.defs.links[item.name];

                            panel.options.fieldList[index].name = name;
                        });
                        panel.name = panel.name + '_' + lang.toLowerCase();
                        panel.label = this.translate('Ownership Information', 'labels', 'Global') + ' ' + lang.toUpperCase();

                        this.panelList.push(panel);
                    });
                }
            }
        }
    })
);
