/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
                },
                params: {
                    options: optionsChannel,
                    translatedOptions: translatedOptionsChannel

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
