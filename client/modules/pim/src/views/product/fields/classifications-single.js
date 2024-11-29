/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/fields/classifications-single', 'views/fields/link',
    Dep => Dep.extend({

        idName: 'classificationsId',

        nameName: 'classificationsName',

        originalIdName: 'classificationsIds',

        originalNameName: 'classificationsNames',

        setup() {
            this.setupTempFields();
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:' + this.idName, () => {
                let name = {}
                if (this.model.get(this.idName)) {
                    name[this.model.get(this.idName)] = this.model.get(this.nameName);
                }

                this.model.set(this.originalIdName, this.model.get(this.idName) ? [this.model.get(this.idName)] : []);
                this.model.set(this.originalNameName, name);
            });

            this.listenTo(this.model, 'change:' + this.originalIdName, this.setupTempFields);
        },

        setupTempFields: function () {
            const classificationId = this.model.get(this.originalIdName)?.at(-1);
            this.model.set(this.idName, classificationId ?? null, {silent: true});
            this.model.set(this.nameName, (this.model.get(this.originalNameName) ?? [])[classificationId] ?? null, {silent: true});
        },

        clearLink: function () {
            if (this.mode === 'search') {
                this.searchData.idValue = null;
                this.searchData.nameValue = null;
            }

            Dep.prototype.clearLink.call(this);
        },

        fetch: function () {
            const data = Dep.prototype.fetch.call(this);
            const ids = [];
            const names = {};

            if (data[this.idName]) {
                ids.push(data[this.idName]);
                names[data[this.idName]] = data[this.nameName]
            }

            data[this.originalIdName] = ids;
            data[this.originalNameName] = names;

            return data;
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