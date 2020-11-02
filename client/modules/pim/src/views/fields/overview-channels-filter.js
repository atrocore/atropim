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

Espo.define('pim:views/fields/overview-channels-filter', 'treo-core:views/fields/dropdown-enum',
    Dep => Dep.extend({

        channels: [],

        optionsList: [
            {
                name: '',
                selectable: true
            },
            {
                name: 'onlyGlobalScope',
                selectable: true
            }
        ],

        setup() {
            this.baseOptionList = Espo.Utils.cloneDeep(this.optionsList);
            this.wait(true);
            this.updateChannels(() => this.wait(false));

            this.listenTo(this.model, 'after:relate after:unrelate', link => {
                if (link === 'channels') {
                    this.updateChannels(() => this.reRender());
                }
            });

            Dep.prototype.setup.call(this);
        },

        updateChannels(callback) {
            this.channels = [];
            this.optionsList = Espo.Utils.cloneDeep(this.baseOptionList);
            const collectionParams = this.getMetadata().get(['entityDefs', 'Channel', 'collection']) || {};
            const sortBy = collectionParams.sortBy || 'createdAt';
            const asc = collectionParams.asc || false;
            this.getFullEntityList(`Product/${this.model.id}/channels`, {
                sortBy: sortBy, asc: asc, select: 'name'
            }, list => {
                this.setChannelsFromList(list);
                this.prepareOptionsList();
                this.updateSelected();
                this.modelKey = this.options.modelKey || this.modelKey;
                this.setDataToModel({[this.name]: this.selected});
                callback();
            });
        },

        updateSelected() {
            if (this.storageKey) {
                let selected = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).selected;
                if (this.optionsList.find(option => option.name === selected)) {
                    this.selected = selected;
                }
            }
            this.selected = this.selected || (this.optionsList.find(option => option.selectable) || {}).name;
        },

        getFullEntityList(url, params, callback, container) {
            if (url) {
                container = container || [];

                let options = params || {};
                options.maxSize = options.maxSize || 200;
                options.offset = options.offset || 0;

                this.ajaxGetRequest(url, options).then(response => {
                    container = container.concat(response.list || []);
                    options.offset = container.length;
                    if (response.total > container.length || response.total === -1) {
                        this.getFullEntity(url, options, callback, container);
                    } else {
                        callback(container);
                    }
                });
            }
        },

        setChannelsFromList(list) {
            list.forEach(item => {
                if (!this.channels.find(channel => channel.id === item.id)) {
                    this.channels.push({
                        id: item.id,
                        name: item.name
                    });
                }
            });
        },

        prepareOptionsList() {
            this.channels.forEach(channel => {
                if (!this.optionsList.find(option => option.name === channel.id)) {
                    this.optionsList.push({
                        name: channel.id,
                        label: channel.name,
                        selectable: true
                    });
                }
            });

            Dep.prototype.prepareOptionsList.call(this);
        }

    })
);
