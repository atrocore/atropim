/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/category/record/edit-small-in-product', 'views/record/edit-small',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.hideField('categoryParent');
        }

    })
);

