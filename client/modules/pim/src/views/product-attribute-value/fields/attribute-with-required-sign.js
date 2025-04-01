/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product-attribute-value/fields/attribute-with-required-sign', 'pim:views/product-attribute-value/fields/attribute',
    Dep => Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const $a = this.$el.find('a');
            let iconsContainer = $a.find('.icons-container');
            if (iconsContainer.size() === 0) {
                $a.append('<sup class="icons-container"></sup>');
                iconsContainer = $a.find('.icons-container');
            }

            iconsContainer.html('');

            if (this.model.get('isRequired')) {
                iconsContainer.append(`<svg class="icon icon-small required-sign" title="${this.translate('Required')}"><use href="client/img/icons/icons.svg#asterisk"></use></svg>`);
            }

            let isPavValueInherited = this.model.get('isPavValueInherited');
            if (isPavValueInherited === true) {
                iconsContainer.append(`<svg class="icon icon-small" title="${this.translate('inherited')}"><use href="client/img/icons/icons.svg#link"></use></svg>`);
            } else if (isPavValueInherited === false) {
                iconsContainer.append(`<svg class="icon icon-small" title="${this.translate('notInherited')}"><use href="client/img/icons/icons.svg#unlink"></use></svg>`);
            }

            if (this.model.get('isVariantSpecificAttribute')) {
                iconsContainer.append(`<svg class="icon icon-small" title="${this.translate('isVariantSpecificAttribute', 'fields', 'ProductAttributeValue')}"><use href="client/img/icons/icons.svg#star"></use></svg>`);
            }

            if (this.model.get('isPavRelationInherited')) {
                iconsContainer.append(`<svg class="icon icon-small" title="${this.translate('isPavRelationInherited', 'fields', 'ProductAttributeValue')}"><use href="client/img/icons/icons.svg#sitemap"></use></svg>`);
            }
        },

    })
);

