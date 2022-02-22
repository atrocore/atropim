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

Espo.define('pim:views/asset/fields/channel', 'views/fields/enum',
    Dep => Dep.extend({

        setupOptions: function () {
            let scope = window.location.hash.split('/').shift().replace('#', '');
            if (scope !== 'Product') {
                return;
            }

            if (this.model.get('channel') === null) {
                this.model.set('channel', '');
            }

            let productId = window.location.hash.split('/').pop();

            let key = 'product-channels-' + productId;

            let currentTime = Math.floor(new Date().getTime() / 1000);
            let storedTime = this.getStorage().get(key + '-time', 'Product');

            if (!storedTime || storedTime < currentTime) {
                this.getStorage().clear(key, 'Product');
            }

            let channels = this.getStorage().get(key, 'Product');
            if (channels === null && this.getAcl().check('Channel', 'read')) {
                this.ajaxGetRequest(`Product/${productId}/channels?silent=true`, null, {async: false}).done(response => {
                    this.getStorage().set(key + '-time', 'Product', currentTime + 5);
                    this.getStorage().set(key, 'Product', response.list);
                    channels = response.list;
                });
            }

            this.params.options = [""];
            this.translatedOptions = {"": "Global"};

            if (channels) {
                channels.forEach(channel => {
                    this.params.options.push(channel.id);
                    this.translatedOptions[channel.id] = channel.name;
                });
            }
        },

    })
);
