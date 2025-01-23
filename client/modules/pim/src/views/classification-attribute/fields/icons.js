/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/classification-attribute/fields/icons', 'views/fields/varchar',
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

            let isCaValueInherited = this.model.get('isCaValueInherited');
            if (isCaValueInherited === true) {
                html += `<span data-caid="${this.model.get('id')}" class="fas fa-link fa-sm" title="${this.translate('inherited')}"></span>`;
            } else if (isCaValueInherited === false) {
                html += `<span data-caid="${this.model.get('id')}" class="fas fa-unlink fa-sm" title="${this.translate('notInherited')}"></span>`;
            }

            return html;
        },

    })
);

