
<table class="table full-table table-striped table-fixed table-bordered-inside" data-name="{{name}}">
    <thead>
    <tr>
        <th>{{ translate 'instance' scope='Synchronization' }}</th>
        <th>
            {{translate 'current' scope='Synchronization' category='labels'}}
        </th>
        {{#each instanceNames}}
        <th>
            {{name}}
        </th>
        {{/each}}
        <th width="25"></th>
    </tr>
    </thead>
    <tbody>
    {{#each attributeList}}
    <tr><td colspan="4"><h5>{{label}}</h5></td></tr>
     {{#each attributes }}
         <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{attributeId}}">
        <td class="cell"><a href="#Attribute/view/{{attributeId}}"> {{attributeName}} ({{attributeChannel}}, {{language}})</a></td>
        <td class="cell current">
            Loading...
        </td>
        {{#each others}}
        <td class="cell other{{index}}">
            Loading...
        </td>
        {{/each}}
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
    </tr>
    {{/each}}
    {{/each}}
    </tbody>
</table>

