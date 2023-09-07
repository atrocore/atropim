/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/scope', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:attributeId', () => {
                    if (this.model.get('attributeId')) {
                        this.ajaxGetRequest(`Attribute/${this.model.get('attributeId')}`).success(attr => {
                            if (attr.defaultScope) {
                                this.model.set('scope', attr.defaultScope);
                                if (attr.defaultChannelId) {
                                    this.model.set('channelId', attr.defaultChannelId);
                                    this.model.set('channelName', attr.defaultChannelName ?? attr.defaultChannelId);
                                }
                            }
                            this.reRender();
                        });
                    }
                });
            }
        },

    })
);

