/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/product/detail', ['pim:views/detail', 'pim:views/product/fields/classifications-single'],
    (Dep, ClassificationSingle) => Dep.extend({

        selectRelatedFilters: {
            classifications: function () {
                return ClassificationSingle.prototype.getSelectFilters.call(this)
            }
        },

        selectBoolFilterLists: {
            attributes: ['notLinkedWithProduct'],
        },

        boolFilterData: {
            attributes: {
                notLinkedWithProduct() {
                    return this.model.id;
                },
            },
        },

        actionNavigateToRoot(data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(function () {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                if (rootUrl !== `#${this.scope}`) {
                    this.getRouter().navigate(rootUrl, {trigger: true});
                } else {
                    const options = {
                        isReturn: true
                    };
                    this.getRouter().navigate(rootUrl, {trigger: false});
                    this.getRouter().dispatch(this.scope, null, options);
                }
            }, this);
        },

        getOverviewFiltersList() {

            let result = Dep.prototype.getOverviewFiltersList.call(this);
            if(this.overviewFilterList.find(v => v.name === 'scopeFilter'))  {
                return this.overviewFilterList;
            }
            if (this.getAcl().check('Channel', 'read')) {
                this.ajaxGetRequest('Channel', {maxSize: 500}, {async: false}).then(data => {
                    let options = [ "allChannels", "linkedChannels", "Global"];
                    let translatedOptions = {
                        "allChannels": this.translate("allChannels"),
                        "linkedChannels": this.translate('linkedChannels'),
                        "Global": this.translate("Global")
                    };
                    if (data.total > 0) {
                        data.list.forEach(item => {
                            options.push(item.id);
                            translatedOptions[item.id] = item.name;
                        });
                    }
                    result.push({
                        name: "scopeFilter",
                        label: this.translate('scopeFilter'),
                        options: options,
                        translatedOptions: translatedOptions,
                        defaultValue: 'linkedChannels'
                    });

                    if(!this.getStorage().get('scopeFilter', this.scope)){
                        this.getStorage().set('scopeFilter', this.scope, ['linkedChannels']);
                    }
                });
            }

            return this.overviewFilterList = result;
        },

    })
);

