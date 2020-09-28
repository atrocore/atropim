{{#if groups.length}}
<div class="group-container">
    {{#each groups}}
    <div class="group" data-name="{{key}}">
        <div class="group-name">
            {{#if id}}
            <a href="#{{../../groupScope}}/view/{{id}}"><strong>{{label}}</strong></a>
            <div class="pull-right btn-group">
                {{#if editable}}
	            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown">
	                <span class="caret"></span>
	            </button>
	            <ul class="dropdown-menu">
	                <li><a href="javascript:" class="action" data-action="unlinkAttributeGroup" data-id="{{id}}">{{translate 'Unlink'}}</a></li>
	            </ul>
                {{/if}}
			</div>
            {{else}}
            <strong>{{label}}</strong>
            {{/if}}
        </div>
        <div class="list-container">&nbsp;{{translate 'Loading...'}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<div class="list-container">{{translate 'No Data'}}</div>
{{/if}}
