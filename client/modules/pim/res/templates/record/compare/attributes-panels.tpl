<div class="row" style="padding-left: 20px">
    <h3>{{translate 'productAttributeValues' category='links' scope=scope}}</h3>
</div>
<table class="table full-table table-striped table-fixed table-bordered-inside">
    <thead>
    <tr>
        <th>{{ translate 'instance' scope='Synchronization' }}</th>
        <th>
            {{translate 'current' scope='Synchronization' category='labels'}}
        </th>
        {{#each distantModels}}
        <th>
            {{_connection}}
        </th>
        {{/each}}
        <th width="25"></th>
    </tr>
    </thead>
    <tbody>
    {{#each attributeList}}
    <tr><td colspan="3"><h5>{{label}}</h5></td></tr>
     {{#each attributes }}
         <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{attributeId}}">
        <td class="cell"><a href="#Attribute/view/{{attributeId}}"> {{attributeName}} ({{attributeChannel}}, {{language}})</a></td>
        <td class="cell current">
            {{{var current ../../this}}}
        </td>
        {{#each others}}
        <td class="cell other{{index}}">
            {{{var other ../../../this}}}
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

