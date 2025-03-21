/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/row-actions/relationship-unlink-and-remove', 'views/record/row-actions/relationship',
    Dep => Dep.extend({

        getActionList() {
            if (this.options.acl.edit) {
                return [
                    {
                        action: 'unlinkRelated',
                        label: 'Unlink',
                        data: {
                            id: this.model.id,
                            cid: this.model.cid
                        }
                    },
                    {
                        action: 'removeRelated',
                        label: 'Remove',
                        data: {
                            id: this.model.id,
                            cid: this.model.cid
                        }
                    }
                ];
            }
        }

    })
);
