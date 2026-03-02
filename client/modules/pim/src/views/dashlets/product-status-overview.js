/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('pim:views/dashlets/product-status-overview', ['views/dashlets/abstract/base','lib!Flotr'],
    (Dep, Flotr) => Dep.extend({

        _template: '<div class="chart-container"></div><div class="legend-container"></div>',

        name: 'ProductStatusOverview',

        decimalMark: '.',

        thousandSeparator: '',

        colorList: ['#6FA8D6', '#4E6CAD', '#EDC555', '#ED8F42', '#DE6666', '#7CC4A4', '#8A7CC2', '#D4729B'],

        textColor: '#333333',

        hoverColor: '#FF3F19',

        legendColumnWidth: 110,

        legendColumnNumber: 6,

        chartContainerMarginBottom: 10,

        chartData: null,

        chartContainer: null,

        legendContainer: null,

        init: function () {
            Dep.prototype.init.call(this);

            this.colorList = this.getThemeManager().getParam('chartColorList') || this.colorList;
            this.textColor = this.getThemeManager().getParam('textColor') || this.textColor;
            this.hoverColor = this.getThemeManager().getParam('hoverColor') || this.hoverColor;

            this.decimalMark = this.getPreferenceValue('decimalMark') || this.decimalMark;
            this.thousandSeparator = this.getPreferenceValue('thousandSeparator') || this.thousandSeparator;

            this.listenToOnce(this, 'after:render', () => {
                $(window).on('resize.chart' + this.name, () => {
                    this.adjustChartContainer();
                    this.draw();
                    this.adjustLegend();
                });
            });

            this.listenToOnce(this, 'remove', () => {
                $(window).off('resize.chart' + this.name)
            });

            this.listenTo(this, 'resize', () => {
                window.setTimeout(() => {
                    this.adjustChartContainer();
                    this.draw();
                    this.adjustLegend();
                }, 10);
            });
        },

        afterRender() {
            this.chartContainer = this.$el.find('.chart-container');
            this.legendContainer = this.$el.find('.legend-container');

            this.buildChart();
        },

        buildChart() {
            this.fetch().then(data => {
                this.chartData = this.prepareData(data);
                let colorList = [],
                    options = this.getMetadata().get(['entityDefs', 'Product', 'fields', 'status', 'options']),
                    optionColors = this.getMetadata().get(['entityDefs', 'Product', 'fields', 'status', 'optionColors']);
                (data.list || []).forEach((item, index) => {
                    colorList.push('#' + optionColors[options.indexOf(item.name)]);
                });
                this.colorList = colorList;

                this.adjustChartContainer();
                this.draw();
                this.adjustLegend();
            });
        },

        getPreferenceValue(key) {
            if (this.getPreferences().has(key)) {
                return this.getPreferences().get(key)
            } else if (this.getConfig().has(key)) {
                return this.getConfig().get(key);
            }
            return null;
        },

        getUrl() {
            return 'Dashlet/ProductsByStatus';
        },

        fetch() {
            return this.ajaxGetRequest(this.getUrl());
        },

        prepareData(data) {
            return (data.list || []).map(item => {
                return {
                    label: this.getLanguage().translateOption(item.name, 'status', 'Product'),
                    data: [[0, item.amount]]
                };
            });
        },

        adjustChartContainer() {
            let height = Math.ceil(this.getLegendHeight()) + this.chartContainerMarginBottom;
            this.chartContainer.css({
                height: `calc(100% - ${height}px)`,
                marginBottom: `${this.chartContainerMarginBottom}px`
            });
        },

        adjustLegend() {
            var number = this.getLegendColumnNumber();
            if (number) {
                let dashletChartLegendBoxWidth = this.getThemeManager().getParam('dashletChartLegendBoxWidth') || 21;
                let containerWidth = this.legendContainer.width();
                let width = Math.floor((containerWidth - dashletChartLegendBoxWidth * number) / number);
                let columnNumber = this.legendContainer.find('> table tr:first-child > td').size() / 2;
                let tableWidth = (width + dashletChartLegendBoxWidth) * columnNumber;
                this.legendContainer.find('> table').css('table-layout', 'fixed').attr('width', tableWidth);
                this.legendContainer.find('td.flotr-legend-label').attr('width', width);
                this.legendContainer.find('td.flotr-legend-color-box').attr('width', dashletChartLegendBoxWidth);
                this.legendContainer.find('td.flotr-legend-label > span').each((i, span) => {
                    span.setAttribute('title', span.textContent);
                });
            }
        },

        getLegendHeight() {
            let lineNumber = Math.ceil(this.chartData.length / this.getLegendColumnNumber());
            let lineHeight = this.getThemeManager().getParam('dashletChartLegendRowHeight') || 19;
            let paddingTopHeight = this.getThemeManager().getParam('dashletChartLegendPaddingTopHeight') || 7;
            return lineNumber ? (lineHeight * lineNumber + paddingTopHeight) : 0;
        },

        getLegendColumnNumber: function () {
            return Math.floor(this.$el.closest('.panel-body').width() / this.legendColumnWidth) || this.legendColumnNumber;
        },

        draw() {
            let self = this;
            Flotr.draw(this.chartContainer.get(0), this.chartData, {
                colors: this.colorList,
                shadowSize: false,
                pie: {
                    show: true,
                    explode: 0,
                    lineWidth: 1,
                    fillOpacity: 1,
                    sizeRatio: 0.8,
                    labelFormatter(total, value) {
                        let percent = self.formatNumber(Math.round(value / total * 10000) / 100);
                        return `<span class="small" style="font-size: 0.8em; color:${self.textColor}">${percent}%</span>`;
                    }
                },
                grid: {
                    horizontalLines: false,
                    verticalLines: false,
                    outline: '',
                },
                yaxis: {
                    showLabels: false,
                },
                xaxis: {
                    showLabels: false,
                },
                mouse: {
                    track: true,
                    relative: true,
                    lineColor: this.hoverColor,
                    trackFormatter: function (obj) {
                        return `${obj.series.label || self.translate('None')}:<br>${self.formatNumber(parseInt(obj.y))} / ${(100 * (obj.fraction || 0)).toFixed(2)}%`;
                    }
                },
                legend: {
                    show: true,
                    noColumns: self.getLegendColumnNumber(),
                    container: self.legendContainer,
                    labelBoxMargin: 0,
                    labelBoxBorderColor: 'transparent',
                    backgroundOpacity: 0,
                    labelFormatter(label) {
                        return `<span style="color: ${self.textColor};">${label}</span>`;
                    },
                }
            });
        },

        formatNumber(value) {
            if (value) {
                let parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);
                return parts.join(this.decimalMark);
            }
            return '';
        },

        actionRefresh: function () {
            this.buildChart();
        },

    })
);

