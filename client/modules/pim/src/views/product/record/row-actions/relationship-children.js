/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/row-actions/relationship-children', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);

            if (!this.model.get('ProductHierarchy__mainChild') && this.options.acl.edit) {
                list.unshift({
                    action: 'setAsMainVariant',
                    label: this.translate('setAsMainVariant'),
                    data: {
                        id: this.model.get('ProductHierarchy__id')
                    }
                });
            }

            return list;
        }
    })
);
