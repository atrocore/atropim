/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/stream/notes/delete-pav', 'views/stream/notes/unrelate', function (Dep) {

    return Dep.extend({
        getEntityName() {
            let name = Dep.prototype.getEntityName.call(this);
            if (this.model.get('language')) {
                name += ' / ' + this.model.get('language')
            }
            return name
        },
    });
});

