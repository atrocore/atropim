/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/record/panels/children', 'views/record/panels/relationship',
    Dep => Dep.extend({

        rowActionsView: 'pim:views/product/record/row-actions/relationship-children',

        actionSetAsMainVariant(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}Hierarchy/${data.id}`, {mainChild: true}).done(entity => {
                this.notify('Updated', 'success');
                this.actionRefresh();
            });
        }
    })
);
