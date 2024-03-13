/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/associated-product/fields/related-product', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['notAssociatedProducts', 'notEntity'],

        boolFilterData: {
            notEntity() {
                return this.model.get('mainProductId');
            },
            notAssociatedProducts() {
                return {mainProductId: this.model.get('mainProductId'), associationId: this.model.get('associationId')};
            }
        },

    })
);
