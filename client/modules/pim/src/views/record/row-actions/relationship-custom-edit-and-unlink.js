/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/row-actions/relationship-custom-edit-and-unlink', 'views/record/row-actions/default',
    Dep=> Dep.extend({

        getActionList: function () {
            let list = [];
            if (this.options.acl.edit) {
                list = list.concat([
                    {
                        action: 'quickEditCustom',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        }
                    },
                    {
                        action: 'unlinkRelatedCustom',
                        label: 'Unlink',
                        data: {
                            id: this.model.id
                        }
                    }
                ]);
            }
            return list;
        },

    })
);


