/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/row-actions/relationship-children', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);
            let model = this.model.relationModel

            if (model && !model.get('mainChild') && this.options.acl.edit) {
                list.unshift({
                    action: 'setAsMainVariant',
                    label: this.translate('setAsMainVariant'),
                    data: {
                        id: model.get('id')
                    }
                });
            }

            return list;
        }
    })
);
