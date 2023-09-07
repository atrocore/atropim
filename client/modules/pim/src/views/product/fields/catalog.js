/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/catalog', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            if (this.mode !== 'search') {
                this.selectBoolFilterList = ['notEntity'];
                this.boolFilterData = {
                    notEntity() {
                        return this.model.get(this.idName);
                    }
                }
            }

            Dep.prototype.setup.call(this);
        }

    })
);
