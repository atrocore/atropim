/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/preview', 'dam:views/asset/fields/preview',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this)
            this.model.set('name', this.model.get('assetName'))
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.$el && this.model.get('isMainImage')) {
                this.$el.parent().addClass('main-image global-main-image');
            }
        }
    })
);