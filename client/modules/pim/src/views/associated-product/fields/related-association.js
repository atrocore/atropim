/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/associated-product/fields/related-association', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive', 'notUsedAssociations'],

        boolFilterData: {
            notUsedAssociations() {
                return {mainProductId: this.model.get('mainProductId'), relatedProductId: this.model.get('relatedProductId')};
            }
        },

    })
);
