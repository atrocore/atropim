/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-asset/record/row-actions/relationship-no-unlink-in-product', 'pim:views/record/row-actions/relationship-asset',
    Dep => Dep.extend({
        getActionList() {
            let list = Dep.prototype.getActionList.call(this);

            if (this.options.acl.delete) {
                if (
                    this.getMetadata().get('scopes.Product.relationInheritance') === true
                    && !(this.getMetadata().get('scopes.Product.unInheritedRelations') || []).includes('productAssets')
                ) {
                    list.push({
                        action: 'removeRelatedHierarchically',
                        label: 'removeHierarchically',
                        data: {
                            id: this.model.id
                        }
                    });
                }
            }

            return list;
        }
    })
);
