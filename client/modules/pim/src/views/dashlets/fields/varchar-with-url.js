/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/fields/varchar-with-url', 'views/fields/varchar',
    Dep => Dep.extend({

        listTemplate: 'pim:dashlets/fields/varchar-with-url/list',

        events: {
            'click a': function (event) {
                event.stopPropagation();
                event.preventDefault();
                let hash = event.currentTarget.hash;
                let scope = hash.substr(1);
                let name = this.model.get(this.name);
                let options = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).options;

                if (options && options.boolFilterList) {
                    let searchData = this.getStorage().get('listSearch', scope);
                    options.boolFilterList.forEach(v => {
                        searchData['bool'][v] = true;
                    });
                    this.getStorage().set('listSearch', scope, searchData);
                }

                this.getRouter().navigate(hash, {trigger: false});
                this.getRouter().dispatch(scope, 'list', options);
            }
        },

        data() {
            let name = this.model.get(this.name);
            let url = ((this.model.getFieldParam(this.name, 'urlMap') || {})[name] || {}).url;

            return {
                hasUrl: !!url,
                label: (this.model.getFieldParam(this.name, 'labelMap') || {})[name] || name,
                url: url
            }
        }

    })
);

