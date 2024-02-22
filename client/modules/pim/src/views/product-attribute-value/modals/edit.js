/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/modals/edit', 'views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        channels: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.ajaxGetRequest(`Product/${this.model.get('productId')}/channels`, null, {async: false}).done(response => {
                if (response.total > 0) {
                    response.list.forEach(record => {
                        this.channels.push(record.id);
                    });
                }
            });

            this.listenTo(this.model, 'change:attributeId', () => {
                const attributeId = this.model.get('attributeId');

                if (attributeId) {
                    const defaultRequired = this.model.get('isRequired');
                    const defaultChannelId = this.model.get('defaultChannelId');

                    this.model.set('isRequired', defaultRequired);

                    if (defaultChannelId && defaultChannelId !== '') {
                        if (this.channels.includes(defaultChannelId)) {
                            this.model.set('channelId', defaultChannelId)
                            this.model.set('channelName', this.model.get('defaultChannelName'));
                        } else {
                            const msg = this.getLanguage().translate('cannotLinkDefaultChannel', 'labels', 'ProductAttributeValue').replace('{channel}', this.model.get('defaultChannelName'))
                            this.notify(msg, 'info');
                        }
                    }
                }
            });
        },

    })
);
