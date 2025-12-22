/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/is-main-image', 'views/fields/bool',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!this.defs.isMassUpdate && this.mode === 'edit') {
                if (!this.isImage()) {
                    this.hide();
                } else {
                    this.show();
                }
            }
        },

        isImage() {
            let model = this.model
            if (model.name === 'ProductFile' || this.getMetadata().get(['scopes', this.model.name, 'derivativeForRelation']) === 'ProductFile') {
                model = this.getParentView().model
            }
            return $.inArray(model.get('extension'), this.getMetadata().get('app.file.image.extensions') || []) !== -1;
        },

    })
);

