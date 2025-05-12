/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('pim:views/product/record/compare', 'views/record/compare', function (Dep) {

    return Dep.extend({

        isComparableLink(link) {
            if(['associatedMainProducts'].includes(link)) {
                return true;
            }

            return Dep.prototype.isComparableLink.call(this, link);
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

    });
});