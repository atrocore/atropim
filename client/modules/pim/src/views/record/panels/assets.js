/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/assets', 'views/record/panels/assets',
    Dep => Dep.extend({

        actionSetAsMainImage(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}Asset/${data.id}`, {isMainImage: true}).done(entity => {
                this.model.trigger('asset:saved');
                this.notify('Saved', 'success');
                this.actionRefresh();
            });
        },

    })
);

