

Espo.define('pim:views/product/modals/select-channel-attributes', 'views/modals/select-records', function (Dep) {

    return Dep.extend({

        multiple: false,

        header: false,

        template: 'modals/select-records',

        createButton: false,

        searchPanel: false,

        scope: null,

        inputLanguageListKeys: [],

        setup: function () {
            this.filters = this.options.filters || {};
            this.boolFilterList = this.options.boolFilterList || [];
            this.primaryFilterName = this.options.primaryFilterName || null;

            if ('multiple' in this.options) {
                this.multiple = this.options.multiple;
            }

            if ('createButton' in this.options) {
                this.createButton = this.options.createButton;
            }

            this.massRelateEnabled = this.options.massRelateEnabled;

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            if (this.multiple) {
                this.buttonList.unshift({
                    name: 'select',
                    style: 'primary',
                    label: 'Select',
                    onClick: function (dialog) {
                        var listView = this.getView('list');
                        var list = listView.getSelected();
                        if (list.length) {
                            this.saveChannelAttributes(list);
                        }
                        dialog.close();
                    }.bind(this),
                });
            }

            this.scope = this.entityType = this.options.scope || this.scope;

            if (this.noCreateScopeList.indexOf(this.scope) !== -1) {
                this.createButton = false;
            }

            this.header = '';
            var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
            this.header += this.getLanguage().translate(this.scope, 'scopeNamesPlural');
            this.header = iconHtml + this.header;
        },

        afterRender() {
            this.ajaxGetRequest(`Markets/Product/${this.options.productId}/attributes`)
                .then(response => {
                    this.getCollectionFactory().create(this.scope, collection => {
                        this.collection = collection;
                        this.defaultSortBy = collection.sortBy;
                        this.defaultAsc = collection.asc;
                        collection.total = 0;

                        let channel = this.options.channels.find(item => item.channelId === this.options.channelId);

                        let inputLanguageList = this.getConfig().get('inputLanguageList');
                        if (Array.isArray(inputLanguageList) && inputLanguageList.length) {
                            this.inputLanguageListKeys = inputLanguageList.map(lang => lang.split('_').reduce((prev, curr) => prev + Espo.utils.upperCaseFirst(curr.toLowerCase()), 'value'));
                        }

                        let existedAttributes = [];
                        (channel.attributes || []).forEach(attribute => {
                            if (!existedAttributes.includes(attribute.attributeId)) {
                                existedAttributes.push(attribute.attributeId);
                            }
                        });
                        response = (response || []).filter(attribute => !existedAttributes.includes(attribute.attributeId));

                        response.forEach(attribute => {
                            if (attribute.attributeId && !channel.attributes.find(item => item.attributeId === attribute.attributeId)) {
                                this.getModelFactory().create(this.scope, model => {
                                    let data ={
                                        name: attribute.name,
                                        type: this.translate(attribute.type, this.scope, 'fields'),
                                        value: attribute.value,
                                        data: attribute.data
                                    };

                                    if (this.inputLanguageListKeys) {
                                        this.inputLanguageListKeys.forEach(item => {
                                            data[item] = attribute[item];
                                        });
                                    }

                                    model.setDefs({
                                        fields: {
                                            name: {
                                                type: 'varchar'
                                            },
                                            type: {
                                                type: 'varchar'
                                            },
                                            value: {
                                                type: 'textMultiLang'
                                            }
                                        }
                                    });
                                    model.id = attribute.productAttributeValueId;
                                    model.set(data);
                                    collection.add(model);
                                    collection._byId[model.id] = model;
                                    collection.total++;
                                });
                            }
                        });

                        this.loadList();
                    }, this);
                });
        },

        loadList() {
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listSelect') ||
                this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') ||
                'views/record/list';
            this.createView('list', viewName, {
                collection: this.collection,
                el: this.containerSelector + ' .list-container',
                selectable: true,
                checkboxes: this.multiple,
                massActionsDisabled: true,
                rowActionsView: false,
                layoutName: 'listSmall',
                searchManager: this.searchManager,
                checkAllResultDisabled: !this.massRelateEnabled,
                buttonsDisabled: true,
                displayTotalCount: false,
                listLayout: [{name: 'name', link: true, notSortable: true}, {name: 'type', notSortable: true}]
            }, function (list) {
                list.once('select', function (models) {
                    this.saveChannelAttributes(models);
                }.bind(this));
                list.render();
            }.bind(this));
        },

        saveChannelAttributes(models) {
            if (models && models.length) {
                this.getModelFactory().create('ChannelProductAttributeValue', emptyModel => {
                    Promise.all(models.map(model => {
                        let currentModel = emptyModel.clone();
                        currentModel.setDefs(emptyModel.defs);
                        let data = {
                            channelId: this.options.channelId,
                            productAttributeId: model.id,
                            value: model.get('value'),
                            data: model.get('data'),
                        };
                        if (this.inputLanguageListKeys) {
                            this.inputLanguageListKeys.forEach(item => {
                                data[item] = model.get(item);
                            });
                        }

                        ['ownerUser', 'assignedUser'].forEach(field => {
                            if (currentModel.hasField(field)) {
                                data[`${field}Id`] = this.getUser().id;
                            }
                        });

                        currentModel.set(data);
                        return currentModel.save();
                    })).then(() => {
                        this.notify('Saved', 'success');
                        this.trigger('after:select');
                        this.getParentView().actionRefresh();
                        this.close();
                    });
                });
            }
        },

    });
});

