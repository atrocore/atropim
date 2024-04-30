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
                this.$el.html(this.inheritedIcon());
            }
        },

        inheritedIcon() {
            let html = '';

            let isCaValueInherited = this.model.get('isCaValueInherited');
            if (isCaValueInherited === true) {
                html = `<a href="javascript:" data-caid="${this.model.get('id')}" class="action unlock-link" title="${this.translate('inherited')}"><span class="fas fa-link fa-sm"></span></a>`;
            } else if (isCaValueInherited === false) {
                html = `<a href="javascript:" data-caid="${this.model.get('id')}" data-action="setCaAsInherited" class="action lock-link" title="${this.translate('setAsInherited')}"><span class="fas fa-unlink fa-sm"></span></a>`;
            }

            return html;
        },

    })
);