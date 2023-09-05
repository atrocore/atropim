/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:controllers/record-tree', 'controllers/record-tree',
    Dep => {

    return Dep.extend({

        defaultAction: 'list',

        listTree: function (options) {
            this.getCollection(function (collection) {
                collection.url = collection.name;
                collection.isFetched = true;
                this.main(this.getViewName('listTree'), {
                    scope: this.name,
                    collection: collection
                });
            });
        },
    });
});
