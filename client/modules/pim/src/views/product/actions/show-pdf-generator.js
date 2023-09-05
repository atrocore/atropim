/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/actions/show-pdf-generator', 'pdf-generator:views/record/actions/show-pdf-generator',
    Dep => Dep.extend({

        showPdfGenerator() {
            this.getModelFactory().create('ProductSheet', model => {
                model.set({entityId: this.model.id});
                model.set({fileName: this.prepareDefaultFileName()});

                this.getChannelsData().then(response => {
                    let channels = [];
                    response.list.forEach(record => {
                        channels.push({
                            "id": record.channelId,
                            "name": record.channelName
                        });
                    });

                    model.set({"productChannels": channels});

                    this.createView('dialog', 'pim:views/product/modals/product-pdf-generator', {
                        scope: 'Product',
                        model: model
                    }, view => view.render());
                });
            })
        },

        getChannelsData() {
            if (this.getAcl().check(this.model.name, 'read')
                && this.getAcl().check('Channel', 'read')
                && this.getAcl().check('ProductChannel', 'read')
            ) {
                return this.ajaxGetRequest(`Product/${this.model.id}/productChannels`);
            } else {
                return new Promise(resolve => resolve({list: [], total: 0}));
            }
        },

        prepareDefaultFileName() {
            let productName = (this.model.get('name') || '').replace(/[^A-Za-z0-9-_]/g, '-');
            let sku = (this.model.get('sku') || '').replace(/[^A-Za-z0-9-_]/g, '-');

            return productName + '-' + sku + '.pdf';
        }
    })
);