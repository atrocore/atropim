/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/channel', 'views/fields/enum',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:render', function () {
                this.controlViewVisibility(this.model.get('template'))
            })

            this.listenTo(this.model, 'changedTemplate', function () {
                this.controlViewVisibility(this.model.get('template'))
            }.bind(this));
        },

        controlViewVisibility(template) {
            if (template) {
                let category = this.model.get('entitiesIds') ? 'templatesEntities' : 'templates';
                let data = this.getMetadata().get(['pdfGenerator', 'Product', category, template]);

                if (!data || !('isChannelTemplate' in data) || data['isChannelTemplate'] === false) {
                    this.hide();
                } else {
                    this.show();
                }
            } else {
                this.hide();
            }
        }
    })
);
