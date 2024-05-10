/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/classifications', 'views/fields/link',
    Dep => Dep.extend({
        setup() {
            let classificationId = this.model.get('classificationsIds')?.at(-1)
            this.model.set('classificationsId', classificationId ?? null);
            this.model.set('classificationsName', (this.model.get('classificationsNames') ?? [])[classificationId] ?? null);
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:classificationsId', () => {
                let name = {}
                if (this.model.get('classificationsId')) {
                    name[this.model.get('classificationsId')] = this.model.get('classificationsName')
                }
                this.model.set('classificationsIds', this.model.get('classificationsId') ? [this.model.get('classificationsId')] : []);
                this.model.set('classificationsNames', name);
            });
        },

        clearLink: function () {
            if (this.mode === 'search') {
                this.searchData.idValue = null;
                this.searchData.nameValue = null;
            }

            Dep.prototype.clearLink.call(this);
        },

        fetchSearch: function () {
            const type = this.$el.find('select.search-type').val();

            if (type === 'is' || type === 'isNot') {
                const searchType = type === 'is' ? 'linkedWith' : 'notLinkedWith';

                return {
                    type: searchType,
                    value: this.searchData.idValue,
                    nameHash: this.searchData.nameValue,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type,
                        idValue: this.searchData.idValue,
                        nameValue: this.searchData.nameValue
                    }
                };
            } else if (type === 'isOneOf' || type === 'isNotOneOf') {
                const searchType = type === 'isOneOf' ? 'linkedWith' : 'notLinkedWith';

                return {
                    type: searchType,
                    value: this.searchData.oneOfIdList,
                    nameHash: this.searchData.oneOfNameHash,
                    oneOfIdList: this.searchData.oneOfIdList,
                    oneOfNameHash: this.searchData.oneOfNameHash,
                    subQuery: this.searchData.subQuery,
                    data: {
                        type: type
                    }
                };
            } else if (type === 'isEmpty') {
                return {
                    type: 'isNotLinked',
                    data: {
                        type: type
                    }
                };
            } else if (type === 'isNotEmpty') {
                return {
                    type: 'isLinked',
                    data: {
                        type: type
                    }
                };
            }
        },
    })
);