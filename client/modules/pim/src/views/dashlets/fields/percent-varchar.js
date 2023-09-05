/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/fields/percent-varchar', 'views/fields/float',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/percent-varchar/list',

        getValueForDisplay() {
            return `${this.model.get(this.name)}%`;
        }

    })
);

