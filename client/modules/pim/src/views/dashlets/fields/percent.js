/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/fields/percent', 'views/fields/float',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/percent/list',

        getValueForDisplay() {
            let total = 0;
            this.model.collection.each(model => total += model.get('amount'));
            return (total ? this.formatNumber(Math.round(this.model.get('amount') / total * 10000) / 100) : 0) + '%';
        }

    })
);

