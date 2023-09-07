/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/main-image', 'views/fields/image',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'asset:saved after:unrelate', () => {
                this.model.fetch().then(() => this.reRender());
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'detail') {
                this.$el.find('.attachment-preview').css({'display': 'block'});
                this.$el.find('img').css({'display': 'block', 'margin': '0 auto'});
            }
        },

    })
);
