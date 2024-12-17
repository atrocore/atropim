<div class="list">
    <table class="table full-table table-striped  table-fixed table-scrolled table-bordered" data-name="{{name}}">
        <thead>
        <tr>
            {{#each columns}}
            <th colspan="{{itemColumnCount}}" class="text-center">
                {{#if link}}
                <a href="#{{../scope}}/view/{{name}}"> {{label}}</a>
                {{else}}
                {{name}}
                {{/if}}
                {{#if _error}}
                <br>
                <span class="danger"> ({{_error}})</span>
                {{/if}}
            </th>
            {{/each}}
        </tr>
        </thead>
        <tbody>
        {{#if noData }}
        <tr>
            <td colspan="{{columnLength}}"> {{ translate 'No Data' }} <td>
        </tr>
        {{else}}
        {{#each attributeList}}
        <tr>
            <td colspan="1"><h5>{{label}}</h5></td>
            <td colspan="{{../columnLength1}}" class="text-center " style="color:#999">{{ translate 'value' scope='ProductAttributeValue' category='fields'}}</td>
        </tr>
        {{#each attributes }}
        <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{key}}">
            <td class="cell"><a href="{{# if instanceUrl}} {{instanceUrl}}/{{/if}}#Attribute/view/{{attributeId}}" {{# if instanceUrl}}target="_blank"{{/if}}> {{attributeName}} ({{attributeChannel}}, {{language}})</a></td>
            <td class="cell current text-center">
                Loading...
            </td>
            {{#each others}}
            <td class="cell other{{index}} text-center">
                Loading...
            </td>
            {{/each}}
            {{#if button }}
            <td class="cell" data-name="buttons">

                <div class="list-row-buttons btn-group pull-right">
                    <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                        <span class="fas fa-ellipsis-v"></span>
                    </button>
                    <ul class="dropdown-menu pull-right">
                        <li> <a class="disabled panel-title">  {{translate 'detailsComparison' scope='Synchronization' category='labels'}}</a></li>
                        <li>
                            <a href="#" class="action" data-action="detailsComparison"
                               data-scope="Attribute"
                               data-id="{{attributeId}}">
                                {{translate 'attribute' scope='Synchronization' category='labels'}}
                            </a>
                        </li>
                        {{#if showQuickCompare }}
                        <li>
                            <a href="#" class="action" data-action="detailsComparison"
                               data-scope="ProductAttributeValue"
                               data-id="{{productAttributeId}}">
                                {{translate 'Value' scope='Attribute' category='labels'}}
                            </a>
                        </li>
                        {{/if}}
                    </ul>
                </div>
            </td>
            {{/if}}
        </tr>
        {{/each}}
        {{/each}}
        {{/if}}
        </tbody>
    </table>
    <div class="panel-scroll hidden" style="display: block;"><div></div></div>
</div>
