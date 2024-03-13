/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/fields/is-multilang', 'views/fields/bool',
    (Dep) => Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:isMultilang', () => {
                if (
                    this.mode === 'edit'
                    && !this.model.get('isMultilang')
                    && this.getMetadata().get(['attributes', this.model.get('type'), 'multilingual'])
                    && !this.model.isNew()
                ) {
                    let model = this.model;
                    Espo.Ui.confirm(this.translate('allLingualAttrsWillDeleted', 'messages', 'Attribute'), {
                        confirmText: this.translate('Apply'),
                        cancelText: this.translate('Cancel'),
                        cancelCallback() {
                            model.set('isMultilang', true);
                        }
                    }, () => {
                        // do nothing
                    });
                }
            });
        },

    })
);

