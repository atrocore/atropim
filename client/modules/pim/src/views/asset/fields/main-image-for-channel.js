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

Espo.define('pim:views/asset/fields/main-image-for-channel', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:isMainImage change:channel', () => {
                this.reRender()
            });
        },

        setupOptions: function () {
            if (this.getHashScope() !== 'Product') {
                return;
            }

            this.params.options = [];
            this.translatedOptions = {};

            if (this.mode === 'edit' && this.getAcl().check('Channel', 'read')) {
                let productId = window.location.hash.split('/').pop();
                this.ajaxGetRequest(`Product/${productId}/productChannels`, null, {async: false}).done(response => {
                    if (response.total > 0) {
                        response.list.forEach(channel => {
                            this.params.options.push(channel.channelId);
                            this.translatedOptions[channel.id] = channel.channelName;
                        });
                    }
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                if (this.getHashScope() !== 'Product' || !this.model.get('isMainImage') || !!this.model.get('channel')) {
                    this.hide();
                } else {
                    this.show();
                }
            }
        },

        getHashScope() {
            return window.location.hash.split('/').shift().replace('#', '');
        },

    })
);
