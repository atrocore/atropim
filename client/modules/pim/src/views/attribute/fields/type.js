/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/fields/type', 'views/fields/enum',
    (Dep) => Dep.extend({

        inlineEditDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.updateOptions();
            this.listenTo(this.model, 'after:save', () => {
                this.updateOptions();
                this.reRender();
            });
        },

        updateOptions() {
            this.params.options = ['']
            this.translatedOptions = {'': ''};

            if (this.model.isNew()) {
                $.each(this.getMetadata().get(['attributes']), (attributeType, attributeDefs) => {
                    this.params.options.push(attributeType);
                    this.translatedOptions[attributeType] = this.getLanguage().translateOption(attributeType, 'type', 'Attribute');
                });
            } else {
                this.params.options.push(this.model.get(this.name));
                this.translatedOptions[this.model.get(this.name)] = this.getLanguage().translateOption(this.model.get(this.name), 'type', 'Attribute');
                $.each(this.getMetadata().get(['attributes', this.model.get(this.name), 'convert'], {}), (attributeType, attributeDefs) => {
                    this.params.options.push(attributeType);
                    this.translatedOptions[attributeType] = this.getLanguage().translateOption(attributeType, 'type', 'Attribute');
                });
            }
        },

    })
);

