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

Espo.define('pim:views/product/modals/product-pdf-generator', 'pdf-generator:views/modals/pdf-generator',
    Dep => Dep.extend({

        template: 'pim:product/modals/product-pdf-generator',

        setupFields() {
            Dep.prototype.setupFields.call(this);

            this.createChannelsView();
        },

        createChannelsView() {
            let productChannels = this.model.get('productChannels') || [];
            let optionsChannel = [''].concat(productChannels.map(channel => channel.id));
            let translatedOptionsChannel = productChannels.reduce((prev, curr) => {
                prev[curr.id] = curr.name;
                return prev;
            }, {'': ''});
            this.model.set({channel: optionsChannel[0]});

            this.createView('channel', 'pim:views/product/fields/channel', {
                name: 'channel',
                el: `${this.options.el} .field[data-name="channel"]`,
                model: this.model,
                scope: this.scope,
                defs: {
                    name: 'channel',
                    params: {
                        options: optionsChannel,
                        translatedOptions: translatedOptionsChannel

                    }
                },
                mode: 'edit',
                inlineEditDisabled: true
            }, view => view.render());
        },

        generatePdfViewUrl() {
            let url = Dep.prototype.generatePdfViewUrl.call(this);

            if (this.model.get('channel')) {
                url += '&' + $.param({'channel': this.model.get('channel')});
            }

            return url;
        },

        prepareDownloadPdfOptions() {
            let data = Dep.prototype.prepareDownloadPdfOptions.call(this);

            if (this.model.get('channel')) {
                data.channel = this.model.get('channel');
            }

            return data;
        }
    })
);
