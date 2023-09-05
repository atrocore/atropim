/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/fields/currencies', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setupOptions() {
            this.params.options = Espo.Utils.clone(this.getConfig().get('currencyList')) || []
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                let baseCurrency = this.getConfig().get('baseCurrency');
                if (!this.selected.includes(baseCurrency)) {
                    this.selected.unshift(baseCurrency);
                    this.model.set({[this.name]: this.selected}, {silent: true});
                    this.reRender();
                }

                this.$element[0].selectize.settings.onDelete = item => item[0] !== baseCurrency;
            }
        }

    })
);
