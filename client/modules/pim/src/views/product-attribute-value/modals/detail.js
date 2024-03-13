/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/modals/detail', 'views/modals/detail',
    Dep => Dep.extend({

        fullFormDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.checkPavScope('edit')) {
                this.removeButton('edit');
            }
        },

        checkPavScope(action) {
            if (this.model.get('tabId')) {
                return this.getAcl().check('AttributeTab', action) && this.getAcl().check('Attribute', action);
            }

            return this.getAcl().check('Attribute', action);
        }
    })
);