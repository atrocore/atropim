/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/plate', 'pim:views/product/list',
    Dep => Dep.extend({

        name: 'plate',

        setup() {
            Dep.prototype.setup.call(this);

            this.collection.maxSize = 20;

            this.getStorage().set('list-view', 'Product', 'plate');
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.plate') || 'views/product/record/plate';
        }

    })
);

