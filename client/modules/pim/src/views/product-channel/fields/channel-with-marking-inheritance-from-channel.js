/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-channel/fields/channel-with-marking-inheritance-from-channel', 'views/fields/link',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let scope = window.location.hash.split('/').shift().replace('#', '');

            if (scope === 'Product' && this.model.get('isInherited')) {
                this.$el.find('a').attr('style', 'font-style: italic');
            }
        },

    })
);
