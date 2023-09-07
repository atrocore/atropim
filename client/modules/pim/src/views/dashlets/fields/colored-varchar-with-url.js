/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/fields/colored-varchar-with-url', 'pim:views/dashlets/fields/varchar-with-url',
    Dep => Dep.extend({

        defaultColor: 'FFFFFF',

        listTemplate: 'pim:dashlets/fields/colored-varchar-with-url/list',

        data() {
            let name = this.model.get(this.name);
            let fieldName = this.options.defs.params.filterField;

            let options = this.getMetadata().get(['entityDefs', 'Product', 'fields', fieldName, 'options']) || {},
                optionColors = this.getMetadata().get(['entityDefs', 'Product', 'fields', fieldName, 'optionColors']) || {};

            const index = options.findIndex(elem => elem === name),
                  color = index !== -1 ? optionColors[index].replace(/^(#+)/, '') : this.defaultColor;

            return _.extend({
                backgroundColor: color,
                color: this.getFontColor(color)
            }, Dep.prototype.data.call(this));
        },

        getFontColor(backgroundColor) {
            if (backgroundColor) {
                let color;
                let r = parseInt(backgroundColor.substr(0, 2), 16);
                let g = parseInt(backgroundColor.substr(2, 2), 16);
                let b = parseInt(backgroundColor.substr(4, 2), 16);
                let l = 1 - ( 0.299 * r + 0.587 * g + 0.114 * b) / 255;
                l < 0.5 ? color = '000' : color = 'fff';
                return color;
            }
        }

    })
);

