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
            <td colspan="{{../columnLength}}"><h5>{{label}}</h5></td>
        </tr>
        {{#each attributes }}
        <tr class="list-row  {{#if  different}} danger {{/if}}" data-id="{{key}}">
            <td class="cell" title="{{label}}"><a href="{{# if instanceUrl}} {{instanceUrl}}/{{/if}}#Attribute/view/{{attributeId}}" {{# if instanceUrl}}target="_blank"{{/if}}> {{{label}}}</a></td>
            <td class="cell current text-center">
                Loading...
            </td>
            {{#each others}}
            <td class="cell other{{index}} text-center">
                Loading...
            </td>
            {{/each}}
        </tr>
        {{/each}}
        {{/each}}
        {{/if}}
        </tbody>
    </table>
</div>
