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
            if (this.getAcl().check(this.model.name, 'read')) {
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