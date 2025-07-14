/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/compare/associated-main-product-instance',
    ['views/record/compare/relationship-instance', 'pim:views/product/record/compare/associated-main-product'],
    (Dep, AssociatedMain) => Dep.extend({
        setup() {
            this.selectFields = ['id', 'name', 'mainImageId', 'mainImageName'];
            Dep.prototype.setup.call(this);
        },

        getFieldColumns(linkedEntity) {
           return AssociatedMain.prototype.getFieldColumns.call(this, linkedEntity);
        },
    })
);