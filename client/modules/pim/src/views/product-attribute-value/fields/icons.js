/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
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

            if (this.model.get('isRequired')) {
                html += `<span class="pull-right fas fa-sm fa-exclamation required-sign" title="${this.translate('Required')}"></span>`;
            }

            if (this.model.get('isVariantSpecificAttribute')) {
                html += `<span class="fas fa-star fa-sm" title="${this.translate('isVariantSpecificAttribute', 'fields', 'ProductAttributeValue')}"></span>`;
            }

            html += this.inheritedIcon();

            return html;
        },

        inheritedIcon() {
            let html = '';

            if (['extensibleEnum', 'extensibleMultiEnum'].includes(this.model.get('attributeType')) && this.model.get('language') !== 'main') {
                return html;
            }

            let isPavValueInherited = this.model.get('isPavValueInherited');
            if (isPavValueInherited === true) {
                html = `<span title="${this.translate('inherited')}" class="fas fa-link fa-sm"></span>`;
            } else if (isPavValueInherited === false) {
                html = `<span title="${this.translate('notInherited')}" class="fas fa-unlink fa-sm"></span>`;
            }

            return html;
        },

    })
);

