/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/fields/icons', 'views/fields/varchar',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list') {
                this.$el.html(this.getIcons());
            }
        },

        getIcons() {
            let html = '';

            html += this.inheritedIcon();

            if (this.model.get('isVariantSpecificAttribute')) {
                html += `<a href="javascript:" class="action unlock-link" title="${this.translate('isVariantSpecificAttribute', 'fields', 'ProductAttributeValue')}"><span class="fas fa-star fa-sm"></span></a>`;
            }

            return html;
        },

        inheritedIcon() {
            let html = '';

            if (['extensibleEnum', 'extensibleMultiEnum'].includes(this.model.get('attributeType')) && this.model.get('language') !== 'main') {
                return html;
            }

            let isPavValueInherited = this.model.get('isPavValueInherited');
            if (isPavValueInherited === true) {
                html = `<a href="javascript:" data-pavid="${this.model.get('id')}" class="action unlock-link" title="${this.translate('inherited')}"><span class="fas fa-link fa-sm"></span></a>`;
            } else if (isPavValueInherited === false) {
                html = `<a href="javascript:" data-pavid="${this.model.get('id')}" data-action="setPavAsInherited" class="action lock-link" title="${this.translate('setAsInherited')}"><span class="fas fa-unlink fa-sm"></span></a>`;
            }

            return html;
        },

    })
);

