/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/record/panels/files', 'views/record/panels/relationship',
    Dep => Dep.extend({

        actionSetAsMainImage(data) {
            this.notify('Saving...');
            this.ajaxPutRequest(`${this.model.urlRoot}File/${data.id}`, {isMainImage: true}).done(entity => {
                this.model.trigger('file:saved');
                this.notify('Saved', 'success');
                this.actionRefresh();
            });
        },

    })
);

