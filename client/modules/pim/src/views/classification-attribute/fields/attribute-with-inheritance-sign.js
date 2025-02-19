/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/attribute-with-inheritance-sign', 'pim:views/classification-attribute/fields/attribute',
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
                iconsContainer.append(`<span class="fas fa-sm fa-asterisk required-sign" title="${this.translate('Required')}"></span>`);
            }

            let isCaValueInherited = this.model.get('isCaValueInherited');
            if (isCaValueInherited === true) {
                iconsContainer.append(`<span title="${this.translate('inherited')}" class="fas fa-link fa-sm"></span>`);
            } else if (isCaValueInherited === false) {
                iconsContainer.append(`<span title="${this.translate('notInherited')}" class="fas fa-unlink fa-sm"></span>`);
            }

            if (this.model.get('isInherited')) {
                iconsContainer.append(`<span class="fa fa-sitemap fa-sm" title="${this.translate('inherited')}"></span>`);
            }
        },

    })
);

