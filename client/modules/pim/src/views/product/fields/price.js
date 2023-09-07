/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/price', 'views/fields/currency',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let currencyRates = this.getConfig().get('currencyRates') || [];
            let baseCurrency = this.getConfig().get('baseCurrency');
            this.currencyList = this.currencyList.filter(item => (item in currencyRates) || item === baseCurrency);
        }

    })
);