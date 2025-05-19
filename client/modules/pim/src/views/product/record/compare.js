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
                let channels = this.getChannels();
                let options = [ "allChannels", "Global"];
                let translatedOptions = {
                    "allChannels": this.translate("allChannels"),
                    "Global": this.translate("Global")
                };

               channels.forEach(item => {
                    options.push(item.id);
                    translatedOptions[item.id] = item.name;
                });

                result.push({
                    name: "scopeFilter",
                    label: this.translate('scopeFilter'),
                    options: options,
                    translatedOptions: translatedOptions,
                    defaultValue: 'allChannels'
                });
            }

            return this.overviewFilterList = result;
        },

        isAllowFieldUsingFilter(field, fieldDef, equalValueForModels) {
           let isAllow = Dep.prototype.isAllowFieldUsingFilter.call(this, field, fieldDef, equalValueForModels);
           if(!isAllow) {
               return false;
           }

           if(!fieldDef['attributeId']) {
               return isAllow;
           }

           let hide = !isAllow;
           const fieldFilter = this.selectedFilters['scopeFilter'] || ['linkedChannels'];
           if(fieldFilter.includes('Global') && fieldDef['channelId']){
               hide = true;
           }else if(!fieldFilter.includes('allChannels') && !fieldFilter.includes('Global') ) {
               hide =  !fieldDef['channelId'] || !fieldFilter.includes(fieldDef['channelId']);
           }

            return !hide;
        },

        getChannels() {
            if(this.channels) {
                return this.channels;
            }
            this.ajaxGetRequest('Channel', {
                select: 'id,name',
                maxSize: 500,
                collectionOnly: true,
            }, {async: false}).then(data => {
                return this.channels = data.list;
            });
            return this.channels;
        }

    });
});