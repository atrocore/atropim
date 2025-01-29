/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/attribute/fields/type', 'views/fields/grouped-enum',
    (Dep) => Dep.extend({

        inlineEditDisabled: true,

        setup: function () {
            this.params.groupTranslation = 'EntityField.groups.type'
            Dep.prototype.setup.call(this);

            this.updateOptions();
            this.listenTo(this.model, 'after:save', () => {
                this.updateOptions();
                this.reRender();
            });
        },

        updateOptions() {
            this.params.options = ['']
            this.params.groups = {};
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

            this.params.options.forEach(attributeType => {
                if (!attributeType) {
                    return
                }
                const group = this.getMetadata().get(['fields', attributeType])?.group || 'other'
                if (!this.params.groups[group]) {
                    this.params.groups[group] = []
                }
                this.params.groups[group].push(attributeType)
            })

            this.params.groups = Object.fromEntries(
                Object.entries(this.params.groups).sort(([v1], [v2]) => {
                    if (v1 === 'other') return 1;
                    if (v2 === 'other') return -1;
                    const order = {numeric: 1, character: 2, date: 3, reference: 4};
                    return (order[v1] || 999) - (order[v2] || 999) ||
                        (this.translatedGroups[v1] || v1).localeCompare((this.translatedGroups[v2] || v2));
                })
            );

            Object.keys(this.params.groups).forEach(group => {
                this.params.groups[group] = this.params.groups[group].sort((v1, v2) => {
                    return this.translate(v1, 'type', 'Attribute').localeCompare(this.translate(v2, 'type', 'Attribute'));
                });
            })
        },

    })
);

