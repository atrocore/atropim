/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/files', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:relate after:unrelate', () => {
                this.collection.reset();
            })

            this.listenTo(this.collection, 'sync', function (c, r, options) {
                if ('list' in r && Array.isArray(r['list'])) {
                    r['list'].forEach(item => {
                        if ('ProductFile__channelId' in item && 'ProductFile__id' in item) {
                            let index = this.collection.models.findIndex(elem => elem.get('id') === item.id && elem.get('ProductFile__channelId') === item.ProductFile__channelId);

                            if (index === -1) {
                                item.originalId = item.id;
                                item.id = item.ProductFile__id;

                                this.collection.add(item);
                            }
                        }
                    });
                }
            }, this);
        },

        actionSetAsMainImage(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}File/${data.id}`, {isMainImage: true}).done(entity => {
                this.model.trigger('file:saved');
                this.notify('Saved', 'success');
                this.actionRefresh();
            });
        },

    })
);

